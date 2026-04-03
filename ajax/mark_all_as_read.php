<?php
include ("../../../inc/includes.php");

Session::checkLoginUser();

// Obtém o ID do usuário logado
$users_id = Session::getLoginUserID();
$success = false;
$total_count = 0;

global $DB;

// Gerar um message_id único para esta operação em lote
$batch_message_id = 'batch_' . time();

// Registrar início da operação
error_log("Iniciando operação de marcar todas as notificações como lidas para o usuário $users_id (message_id: $batch_message_id)");

try {
    // 0. Obter permissões do perfil
    $active_profile = $_SESSION['glpiactiveprofile'] ?? [];
    $can_see_own = isset($active_profile['own_ticket']) && $active_profile['own_ticket'] > 0;
    $can_see_observe = isset($active_profile['observe_ticket']) && $active_profile['observe_ticket'] > 0;
    $can_see_assign = isset($active_profile['assign_ticket']) && $active_profile['assign_ticket'] > 0;

    // 1. Identificar todas as notificações não lidas seguindo EXATAMENTE a mesma lógica do front/index.php
    $union_parts = [];

    // Notificações de respostas de técnicos (technician_response)
    if ($can_see_own) {
        $union_parts[] = "SELECT t.id AS ticket_id, CAST(tf.id AS CHAR) AS followup_id
                         FROM glpi_tickets t
                         INNER JOIN glpi_tickets_users tu ON t.id = tu.tickets_id AND tu.type = 1 AND tu.users_id = $users_id
                         INNER JOIN glpi_itilfollowups tf ON t.id = tf.items_id AND tf.itemtype = 'Ticket'
                         LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.followup_id = CAST(tf.id AS CHAR))
                         WHERE tf.users_id <> $users_id AND v.id IS NULL AND t.status != 6 AND tf.is_private = 0
                         AND EXISTS (SELECT 1 FROM glpi_tickets_users tech_user WHERE tech_user.tickets_id = t.id AND tech_user.users_id = tf.users_id AND tech_user.type = 2)";
    }

    // Observadores individuais
    if ($can_see_observe) {
        $union_parts[] = "SELECT t.id AS ticket_id, CAST(t.id + 20000000 AS CHAR) AS followup_id
                         FROM glpi_tickets t
                         INNER JOIN glpi_tickets_users tu ON t.id = tu.tickets_id AND tu.type = 3 AND tu.users_id = $users_id
                         LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.followup_id = CAST(t.id + 20000000 AS CHAR))
                         WHERE v.id IS NULL AND t.status IN (1, 2, 3, 4)
                         AND NOT EXISTS (SELECT 1 FROM glpi_tickets_users r WHERE r.tickets_id = t.id AND r.users_id = $users_id AND r.type = 1)
                         AND NOT EXISTS (SELECT 1 FROM glpi_tickets_users tech WHERE tech.tickets_id = t.id AND tech.users_id = $users_id AND tech.type = 2)";
    }

    // Grupo Observador
    if ($can_see_observe) {
        $union_parts[] = "SELECT t.id AS ticket_id, CAST(t.id + 20000000 AS CHAR) AS followup_id
                         FROM glpi_tickets t
                         INNER JOIN glpi_groups_tickets gt ON t.id = gt.tickets_id AND gt.type = 3
                         INNER JOIN glpi_groups_users gu ON gt.groups_id = gu.groups_id AND gu.users_id = $users_id
                         INNER JOIN glpi_itilfollowups tf ON t.id = tf.items_id AND tf.itemtype = 'Ticket'
                         LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.followup_id = CAST(t.id + 20000000 AS CHAR))
                         WHERE v.id IS NULL AND t.status != 6
                         AND tf.date > (SELECT COALESCE(MAX(date), '1970-01-01') FROM glpi_itilfollowups tf2 WHERE tf2.items_id = t.id AND tf2.itemtype = 'Ticket' AND tf2.users_id = $users_id)";
    }

    // Chamados Recusados
    if ($can_see_own) {
        $union_parts[] = "SELECT t.id AS ticket_id, CAST(its.id AS CHAR) AS followup_id
                         FROM glpi_tickets t
                         INNER JOIN glpi_tickets_users tu ON t.id = tu.tickets_id AND tu.users_id = $users_id
                         INNER JOIN (SELECT items_id, MAX(id) as latest_id FROM glpi_itilsolutions WHERE status = 4 AND itemtype = 'Ticket' GROUP BY items_id) latest ON t.id = latest.items_id
                         INNER JOIN glpi_itilsolutions its ON its.id = latest.latest_id
                         LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.followup_id = CAST(its.id AS CHAR))
                         WHERE its.users_id_approval <> $users_id AND v.id IS NULL AND t.status != 6";
    }

    // Validações (Como validador ou como requerente)
    $union_parts[] = "SELECT t.id AS ticket_id, CAST(tv.id AS CHAR) AS followup_id
                     FROM glpi_ticketvalidations tv
                     INNER JOIN glpi_tickets t ON tv.tickets_id = t.id AND t.status != 6
                     LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.followup_id = CAST(tv.id AS CHAR))
                     WHERE tv.status = 2 AND v.id IS NULL
                     AND (tv.users_id_validate = $users_id OR EXISTS (SELECT 1 FROM glpi_tickets_users tu WHERE tu.tickets_id = t.id AND tu.users_id = $users_id AND tu.type = 1))";

    // Respostas de Validação
    if ($can_see_own || $can_see_assign) {
        $union_parts[] = "SELECT t.id AS ticket_id, CONCAT('validation_response_', tv.id) AS followup_id
                         FROM glpi_tickets t
                         INNER JOIN glpi_tickets_users tu ON t.id = tu.tickets_id AND tu.users_id = $users_id AND tu.type = 1
                         INNER JOIN glpi_ticketvalidations tv ON t.id = tv.tickets_id AND tv.users_id = $users_id
                         LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.followup_id = CONCAT('validation_response_', tv.id))
                         WHERE t.status != 6 AND (tv.status = 3 OR tv.status = 4) AND v.id IS NULL";
    }

    // Mudanças de Status
    if ($can_see_own) {
        $union_parts[] = "SELECT t.id AS ticket_id, CONCAT('status_', t.id, '_', t.status) AS followup_id
                         FROM glpi_tickets t
                         INNER JOIN glpi_tickets_users tu ON t.id = tu.tickets_id AND tu.type = 1 AND tu.users_id = $users_id
                         LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND (v.followup_id = CONCAT('status_', t.id, '_', t.status) OR v.followup_id = CONCAT('status_', t.id, '_any')))
                         WHERE v.id IS NULL AND t.status IN (2, 3, 4, 5)";
    }

    // Motivos de Pendência
    if ($can_see_own && $DB->tableExists('glpi_pendingreasons_items')) {
        $union_parts[] = "SELECT t.id AS ticket_id, CONCAT('pending_', t.id, '_', pri.pendingreasons_id) AS followup_id
                         FROM glpi_tickets t
                         INNER JOIN glpi_tickets_users tu ON t.id = tu.tickets_id AND tu.type = 1 AND tu.users_id = $users_id
                         INNER JOIN glpi_pendingreasons_items pri ON t.id = pri.items_id AND pri.itemtype = 'Ticket'
                         LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.followup_id = CONCAT('pending_', t.id, '_', pri.pendingreasons_id))
                         WHERE v.id IS NULL AND t.status = 3";
    }

    if (!empty($union_parts)) {
        // Implementação otimizada com inserção em massa para melhor desempenho
        $batch_query = "INSERT IGNORE INTO glpi_plugin_ticketanswers_views (ticket_id, users_id, followup_id, viewed_at, message_id)
                        SELECT DISTINCT ticket_id, $users_id, followup_id, NOW(), '$batch_message_id'
                        FROM (" . implode(" UNION ", $union_parts) . ") AS to_mark";
        
        $DB->query($batch_query);
        $total_count = $DB->affectedRows();
        $success = true;
        
        error_log("Marcadas $total_count notificações como lidas por meio de query otimizada (message_id: $batch_message_id)");
    } else {
        $success = true;
        $total_count = 0;
    }
    
} catch (Exception $e) {
    error_log("Erro ao marcar notificações como lidas: " . $e->getMessage());
    $success = false;
}

// Se for uma requisição AJAX, retornar JSON
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    echo json_encode([
        'success' => $success, 
        'count' => $total_count,
        'message_id' => $batch_message_id
    ]);
    exit();
}

// Se não for AJAX, redirecionar para a página de notificações
Html::redirect($CFG_GLPI["root_doc"] . "/plugins/ticketanswers/front/index.php");
