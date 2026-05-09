<?php
include ("../../../inc/includes.php");

Session::checkLoginUser();

// Verificar se os parâmetros necessários foram fornecidos
$ticket_id = isset($_GET['ticket_id']) ? intval($_GET['ticket_id']) : 0;
$followup_id = isset($_GET['followup_id']) ? $_GET['followup_id'] : 0;
$notification_type = isset($_GET['type']) ? $_GET['type'] : 'followup';
$message_id = isset($_GET['message_id']) ? $_GET['message_id'] : null;
$users_id = Session::getLoginUserID();
$success = false;

error_log("Marcando notificação como lida: tipo=$notification_type, ticket=$ticket_id, followup=$followup_id, user=$users_id, message_id=$message_id");

if ($ticket_id > 0) {
    global $DB;
    
    // Determinar o valor de followup_id com base no tipo de notificação
    $actual_followup_id = $followup_id;
    
    // Mapeamento de IDs especiais (Legado e Cálculos)
    if ($notification_type === 'group') {
        $actual_followup_id = $ticket_id + 10000000;
    } else if ($notification_type === 'observer' || $notification_type === 'group_observer') {
        $actual_followup_id = $ticket_id + 20000000;
    } else if ($notification_type === 'assigned') {
        $actual_followup_id = $ticket_id + 30000000;
    } else if ($notification_type === 'pending_reason' && is_numeric($followup_id)) {
        // Se for antigo (só número), usa o cálculo. Se for string (pending_...), usa a string.
        $actual_followup_id = $ticket_id + 50000000;
    }
    
    // Para todos os outros casos, se for uma string (contém letras ou sublinhados), não converter!
    if (!is_numeric($actual_followup_id)) {
        $actual_followup_id = $DB->escape($actual_followup_id);
    }
    
    error_log("DEBUG: Processamento de ID - tipo=$notification_type, ticket=$ticket_id, calculado=$actual_followup_id");
    
    // Para notificações de status, tratamento especial com aspas
    if ($notification_type === 'status_change') {
        try {
            $current_datetime = date('Y-m-d H:i:s');
            
            // Preparar o valor do message_id para a query
            $message_id_value = $message_id ? "'" . $DB->escape($message_id) . "'" : "NULL";
            
            // Construir a query com aspas para o formato de string do status_change
            $query = "INSERT INTO glpi_plugin_ticketanswers_views
                     (ticket_id, users_id, followup_id, viewed_at, message_id)
                     VALUES ('$ticket_id', $users_id, '$actual_followup_id', '$current_datetime', $message_id_value)
                     ON DUPLICATE KEY UPDATE viewed_at = '$current_datetime'";
            
            error_log("Executando query para status_change: $query");
            
            // Executar a query
            $insertResult = $DB->query($query);
            
            if ($insertResult) {
                error_log("Registro de status_change inserido ou atualizado com sucesso");
                
                // Tentar também o formato alternativo
                $alternative_id = $ticket_id + 40000000;
                $query = "INSERT INTO glpi_plugin_ticketanswers_views
                         (ticket_id, users_id, followup_id, viewed_at, message_id)
                         VALUES ('$ticket_id', $users_id, '$alternative_id', '$current_datetime', $message_id_value)
                         ON DUPLICATE KEY UPDATE viewed_at = '$current_datetime'";
                         
                $DB->query($query); // Executar mas não verificar resultado, é apenas uma tentativa adicional
                
                $success = true;
            } else {
                $error = $DB->error();
                error_log("Erro ao inserir registro de status_change: " . $error);
                $success = false;
            }
        } catch (Exception $e) {
            error_log("Exceção ao processar notificação de status: " . $e->getMessage());
            $success = false;
        }
    } else {
        // Para outros tipos de notificação, usar o código original
        try {
            // Preparar a data atual
            $current_datetime = date('Y-m-d H:i:s');
            
            // Preparar o valor do message_id para a query
            $message_id_value = $message_id ? "'" . $DB->escape($message_id) . "'" : "NULL";
            
            // Construir a query - sem alteração para tipos não-status
            $query = "INSERT INTO glpi_plugin_ticketanswers_views
                     (ticket_id, users_id, followup_id, viewed_at, message_id)
                     VALUES ($ticket_id, $users_id, $actual_followup_id, '$current_datetime', $message_id_value)
                     ON DUPLICATE KEY UPDATE viewed_at = '$current_datetime'";
            
            error_log("Executando query: $query");
            
            // Executar a query
            $insertResult = $DB->query($query);
            
            if ($insertResult) {
                error_log("Registro inserido ou atualizado com sucesso");
                $success = true;
            } else {
                $error = $DB->error();
                error_log("Erro ao inserir/atualizar registro: " . $error);
                $success = false;
            }
        } catch (Exception $e) {
            error_log("Exceção ao inserir/atualizar registro: " . $e->getMessage());
            $success = false;
        }
    }

    // Marcar também TODAS as outras notificações deste mesmo ticket como lidas para este usuário
    try {
        $union_parts = [];

        // Acompanhamentos (Followups)
        $union_parts[] = "(
            SELECT t.id AS ticket_id, CAST(tf.id AS CHAR) AS followup_id
            FROM glpi_tickets t
            INNER JOIN glpi_itilfollowups tf ON t.id = tf.items_id AND tf.itemtype = 'Ticket'
            LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.followup_id = CAST(tf.id AS CHAR))
            WHERE t.id = $ticket_id AND tf.users_id <> $users_id AND v.id IS NULL AND t.status != 6 AND tf.is_private = 0
            AND tf.date > (
                SELECT COALESCE(MAX(date), '1970-01-01')
                FROM glpi_itilfollowups tf2
                WHERE tf2.items_id = t.id AND tf2.itemtype = 'Ticket' AND tf2.users_id = $users_id
            )
        )";

        // Observador Individual (notificações do chamado em si)
        $union_parts[] = "(
            SELECT t.id AS ticket_id, CAST(t.id + 20000000 AS CHAR) AS followup_id
            FROM glpi_tickets t
            LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.ticket_id = t.id AND v.followup_id = CAST(t.id + 20000000 AS CHAR))
            WHERE t.id = $ticket_id AND v.id IS NULL AND t.status IN (1, 2, 3, 4)
        )";

        // Chamados Recusados
        $union_parts[] = "(
            SELECT t.id AS ticket_id, CAST(its.id AS CHAR) AS followup_id
            FROM glpi_tickets t
            INNER JOIN (SELECT items_id, MAX(id) as latest_id FROM glpi_itilsolutions WHERE status = 4 AND itemtype = 'Ticket' GROUP BY items_id) latest ON t.id = latest.items_id
            INNER JOIN glpi_itilsolutions its ON its.id = latest.latest_id
            LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.followup_id = CAST(its.id AS CHAR))
            WHERE t.id = $ticket_id AND its.users_id_approval <> $users_id AND v.id IS NULL AND t.status != 6
        )";

        // Validações (Como validador)
        $union_parts[] = "(
            SELECT t.id AS ticket_id, CAST(tv.id AS CHAR) AS followup_id
            FROM glpi_ticketvalidations tv
            INNER JOIN glpi_tickets t ON tv.tickets_id = t.id AND t.status != 6
            LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.ticket_id = t.id AND v.followup_id = CAST(tv.id AS CHAR))
            WHERE t.id = $ticket_id AND tv.status = 2 AND v.id IS NULL AND tv.users_id_validate = $users_id
        )";

        // Respostas de Validação
        $union_parts[] = "(
            SELECT t.id AS ticket_id, CONCAT('validation_response_', tv.id) AS followup_id
            FROM glpi_tickets t
            INNER JOIN glpi_ticketvalidations tv ON t.id = tv.tickets_id AND tv.users_id = $users_id
            LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.ticket_id = t.id AND v.followup_id = CONCAT('validation_response_', tv.id))
            WHERE t.id = $ticket_id AND t.status != 6 AND (tv.status = 3 OR tv.status = 4) AND v.id IS NULL
        )";

        // Mudanças de Status
        $union_parts[] = "(
            SELECT t.id AS ticket_id, CONCAT('status_', t.id, '_', t.status) AS followup_id
            FROM glpi_tickets t
            LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.ticket_id = t.id AND (v.followup_id = CONCAT('status_', t.id, '_', t.status) OR v.followup_id = CONCAT('status_', t.id, '_any')))
            WHERE t.id = $ticket_id AND v.id IS NULL AND t.status IN (2, 3, 4, 5)
        )";

        // Pendência com Motivo
        $has_pending_tables = $DB->tableExists('glpi_pendingreasons') && $DB->tableExists('glpi_pendingreasons_items');
        if ($has_pending_tables) {
            $union_parts[] = "(
                SELECT t.id AS ticket_id, CONCAT('pending_', t.id, '_', pri.pendingreasons_id) AS followup_id
                FROM glpi_tickets t
                INNER JOIN glpi_pendingreasons_items pri ON t.id = pri.items_id AND pri.itemtype = 'Ticket'
                LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.ticket_id = t.id AND v.followup_id = CONCAT('pending_', t.id, '_', pri.pendingreasons_id))
                WHERE t.id = $ticket_id AND v.id IS NULL AND t.status = 3
            )";
        }

        if (!empty($union_parts)) {
            $subquery = implode(" UNION ", $union_parts);
            $bulk_insert_sql = "
                INSERT INTO glpi_plugin_ticketanswers_views (ticket_id, users_id, followup_id, viewed_at)
                SELECT DISTINCT ticket_id, $users_id, followup_id, '$current_datetime'
                FROM ($subquery) AS temp
                ON DUPLICATE KEY UPDATE viewed_at = '$current_datetime'
            ";
            $DB->query($bulk_insert_sql);
        }
    } catch (Exception $e) {
        error_log("Erro ao marcar todas as notificações do ticket $ticket_id como lidas: " . $e->getMessage());
    }
}

// Função auxiliar para marcar notificação como lida
function markNotificationAsRead($DB, $ticket_id, $users_id, $followup_id, $current_datetime = null, $message_id = null) {
    if ($current_datetime === null) {
        $current_datetime = date('Y-m-d H:i:s');
    }
    
    // Preparar o valor do message_id para a query
    $message_id_value = $message_id ? "'" . $DB->escape($message_id) . "'" : "NULL";
    
    // Verificar se followup_id é uma string que deve ser citada
    $followup_id_value = (is_numeric($followup_id) && !is_string($followup_id)) ? $followup_id : "'" . $DB->escape($followup_id) . "'";
    
    // Construir a query com ON DUPLICATE KEY UPDATE
    $query = "INSERT INTO glpi_plugin_ticketanswers_views
             (ticket_id, users_id, followup_id, viewed_at, message_id)
             VALUES ($ticket_id, $users_id, $followup_id_value, '$current_datetime', $message_id_value)
             ON DUPLICATE KEY UPDATE viewed_at = '$current_datetime'";
    
    error_log("Executando query: $query");
    
    // Executar a query
    $insertResult = $DB->query($query);
    
    if ($insertResult) {
        error_log("Registro inserido ou atualizado com sucesso para followup_id=$followup_id");
        return true;
    } else {
        $error = $DB->error();
        error_log("Erro ao inserir/atualizar registro para followup_id=$followup_id: " . $error);
        return false;
    }
}

// Se for uma requisição AJAX, retornar JSON
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    header('Content-Type: application/json');
    echo json_encode(['success' => $success]);
    exit();
}

// Se não for AJAX, redirecionar para a página do ticket
if ($ticket_id > 0) {
    Html::redirect($CFG_GLPI["root_doc"] . "/front/ticket.form.php?id=" . $ticket_id);
} else {
    Html::redirect($CFG_GLPI["root_doc"] . "/plugins/ticketanswers/front/index.php");
}
