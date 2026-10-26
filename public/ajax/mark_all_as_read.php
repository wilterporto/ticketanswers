<?php
if (!defined('GLPI_ROOT')) {
    include (__DIR__ . "/../../../../inc/includes.php");
}

Session::checkLoginUser();

// Obtém o ID do usuário logado
$users_id = Session::getLoginUserID();
$success = false;
$count = 0;

global $DB;

// Gerar um message_id único para esta operação em lote
$batch_message_id = 'batch_' . time();

// Registrar início da operação
error_log("Iniciando operação de marcar todas as notificações como lidas para o usuário $users_id (message_id: $batch_message_id)");

try {
    // 0. Verificar tabelas de pendência
    $has_pending_tables = $DB->tableExists('glpi_pendingreasons') && $DB->tableExists('glpi_pendingreasons_items');

    // Condição unificada para verificar o envolvimento do usuário no chamado (Requerente, Atribuído ou Observador - direto ou via grupo)
    $actor_condition = "(
        t.users_id_recipient = $users_id
        OR EXISTS (SELECT 1 FROM glpi_tickets_users tu WHERE tu.tickets_id = t.id AND tu.users_id = $users_id AND tu.type IN (1, 2, 3))
        OR EXISTS (SELECT 1 FROM glpi_groups_tickets gt INNER JOIN glpi_groups_users gu ON gt.groups_id = gu.groups_id WHERE gt.tickets_id = t.id AND gu.users_id = $users_id AND gt.type IN (1, 2, 3))
    )";

    // 1. Construir consultas espelhadas nas de front/index.php
    $union_parts = [];

    // Acompanhamentos (Followups)
    $union_parts[] = "(
        SELECT t.id AS ticket_id, CAST(tf.id AS CHAR) AS followup_id
        FROM glpi_tickets t
        INNER JOIN glpi_itilfollowups tf ON t.id = tf.items_id AND tf.itemtype = 'Ticket'
        LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.followup_id = CAST(tf.id AS CHAR))
        WHERE tf.users_id <> $users_id AND v.id IS NULL AND t.status != 6 AND tf.date > DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND (tf.is_private = 0 OR (tf.is_private = 1 AND EXISTS (SELECT 1 FROM glpi_tickets_users tu_tech WHERE tu_tech.tickets_id = t.id AND tu_tech.users_id = $users_id AND tu_tech.type = 2)))
        AND $actor_condition
        AND tf.date > (
            SELECT COALESCE(MAX(date), '1970-01-01')
            FROM glpi_itilfollowups tf2
            WHERE tf2.items_id = t.id AND tf2.itemtype = 'Ticket' AND tf2.users_id = $users_id
        )
    )";

    // Tarefas (Tasks)
    $union_parts[] = "(
        SELECT t.id AS ticket_id, CONCAT('task_', tt.id) AS followup_id
        FROM glpi_tickets t
        INNER JOIN glpi_tickettasks tt ON t.id = tt.tickets_id
        LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.followup_id = CONCAT('task_', tt.id))
        WHERE tt.users_id <> $users_id AND v.id IS NULL AND t.status != 6 AND tt.date > DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND (tt.is_private = 0 OR (tt.is_private = 1 AND EXISTS (SELECT 1 FROM glpi_tickets_users tu_tech WHERE tu_tech.tickets_id = t.id AND tu_tech.users_id = $users_id AND tu_tech.type = 2)))
        AND $actor_condition
    )";

    // Soluções Propostas
    $union_parts[] = "(
        SELECT t.id AS ticket_id, CONCAT('solution_', its.id) AS followup_id
        FROM glpi_tickets t
        INNER JOIN glpi_itilsolutions its ON t.id = its.items_id AND its.itemtype = 'Ticket'
        LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.followup_id = CONCAT('solution_', its.id))
        WHERE its.users_id <> $users_id AND its.status IN (1, 2) AND v.id IS NULL AND t.status != 6
        AND $actor_condition AND its.date_creation > DATE_SUB(NOW(), INTERVAL 30 DAY)
    )";

    // Chamados Recusados
    $union_parts[] = "(
        SELECT t.id AS ticket_id, CAST(its.id AS CHAR) AS followup_id
        FROM glpi_tickets t
        INNER JOIN (SELECT items_id, MAX(id) as latest_id FROM glpi_itilsolutions WHERE status = 4 AND itemtype = 'Ticket' GROUP BY items_id) latest ON t.id = latest.items_id
        INNER JOIN glpi_itilsolutions its ON its.id = latest.latest_id
        LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.followup_id = CAST(its.id AS CHAR))
        WHERE its.users_id_approval <> $users_id AND v.id IS NULL AND t.status != 6
        AND its.date_approval > DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND $actor_condition
    )";

    // Validações (Como validador ou participante)
    $union_parts[] = "(
        SELECT t.id AS ticket_id, CAST(tv.id AS CHAR) AS followup_id
        FROM glpi_ticketvalidations tv
        INNER JOIN glpi_tickets t ON tv.tickets_id = t.id AND t.status != 6
        LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.ticket_id = t.id AND v.followup_id = CAST(tv.id AS CHAR))
        WHERE tv.status = 2 AND tv.users_id <> $users_id AND v.id IS NULL
        AND (tv.users_id_validate = $users_id OR $actor_condition)
        AND tv.submission_date > DATE_SUB(NOW(), INTERVAL 90 DAY)
    )";

    // Respostas de Validação
    $union_parts[] = "(
        SELECT t.id AS ticket_id, CONCAT('validation_response_', tv.id) AS followup_id
        FROM glpi_tickets t
        INNER JOIN glpi_ticketvalidations tv ON t.id = tv.tickets_id
        LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.ticket_id = t.id AND v.followup_id = CONCAT('validation_response_', tv.id))
        WHERE t.status != 6 AND (tv.status = 3 OR tv.status = 4) AND tv.users_id_validate <> $users_id AND v.id IS NULL
        AND tv.validation_date > DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND $actor_condition
    )";

    // Mudanças de Status
    $union_parts[] = "(
        SELECT t.id AS ticket_id, CONCAT('status_', t.id, '_', t.status) AS followup_id
        FROM glpi_tickets t
        LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.ticket_id = t.id AND (v.followup_id = CONCAT('status_', t.id, '_', t.status) OR v.followup_id = CONCAT('status_', t.id, '_any')))
        WHERE v.id IS NULL AND t.status IN (2, 3, 4, 5) AND t.date_mod > DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND $actor_condition
    )";

    // Observador / Inicial
    $union_parts[] = "(
        SELECT t.id AS ticket_id, CAST(t.id + 20000000 AS CHAR) AS followup_id
        FROM glpi_tickets t
        LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.ticket_id = t.id AND v.followup_id = CAST(t.id + 20000000 AS CHAR))
        WHERE v.id IS NULL AND t.status IN (1, 2, 3, 4) AND t.date_creation > DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND $actor_condition
    )";

    // Pendência com Motivo
    if ($has_pending_tables) {
        $union_parts[] = "(
            SELECT t.id AS ticket_id, CONCAT('pending_', t.id, '_', pri.pendingreasons_id) AS followup_id
            FROM glpi_tickets t
            INNER JOIN glpi_pendingreasons_items pri ON t.id = pri.items_id AND pri.itemtype = 'Ticket'
            INNER JOIN glpi_pendingreasons pr ON pri.pendingreasons_id = pr.id
            LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.ticket_id = t.id AND v.followup_id = CONCAT('pending_', t.id, '_', pri.pendingreasons_id))
            WHERE v.id IS NULL AND t.status = 3 AND pri.last_bump_date > DATE_SUB(NOW(), INTERVAL 7 DAY)
            AND $actor_condition
        )";
    }

    if (!empty($union_parts)) {
        $subquery = implode(" UNION ", $union_parts);

        // Obter contagem exata de notificações antes da inserção
        $count_sql = "SELECT COUNT(*) AS total FROM (SELECT DISTINCT ticket_id, followup_id FROM ($subquery) AS temp) AS total_temp";
        $count_result = $DB->doQuery($count_sql);
        if ($count_result && $DB->numrows($count_result) > 0) {
            $count_data = $DB->fetchAssoc($count_result);
            $count = (int)$count_data['total'];
        }

        if ($count > 0) {
            // Bulk insert de alta performance em uma única query
            $bulk_insert_query = "
                INSERT INTO glpi_plugin_ticketanswers_views (ticket_id, users_id, followup_id, viewed_at, message_id)
                SELECT DISTINCT ticket_id, $users_id, followup_id, NOW(), '$batch_message_id'
                FROM ($subquery) AS unread_notifications
                ON DUPLICATE KEY UPDATE viewed_at = NOW(), message_id = '$batch_message_id'
            ";

            $result = $DB->doQuery($bulk_insert_query);
            if ($result) {
                $success = true;
                error_log("Lote concluído via Bulk Insert: $count notificações marcadas como lidas.");
            }
        } else {
            $success = true;
        }
    } else {
        $success = true;
    }

} catch (Exception $e) {
    error_log("Erro em mark_all_as_read: " . $e->getMessage());
}

// Retorno AJAX
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    echo json_encode([
        'success' => $success, 
        'count' => $count,
        'message_id' => $batch_message_id
    ]);
    exit();
}

// Redirecionamento Fallback
Html::redirect($CFG_GLPI["root_doc"] . "/plugins/ticketanswers/front/index.php");
