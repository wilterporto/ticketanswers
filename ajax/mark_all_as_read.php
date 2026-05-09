<?php
include ("../../../inc/includes.php");

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

    // 1. Construir consultas espelhadas nas de front/index.php (usando os mesmos filtros e joins)
    $union_parts = [];

    // Acompanhamentos (Followups) - Usuário envolvido diretamente ou por qualquer grupo
    $union_parts[] = "(
        SELECT t.id AS ticket_id, CAST(tf.id AS CHAR) AS followup_id
        FROM glpi_tickets t
        INNER JOIN glpi_itilfollowups tf ON t.id = tf.items_id AND tf.itemtype = 'Ticket'
        LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.followup_id = CAST(tf.id AS CHAR))
        WHERE tf.users_id <> $users_id AND v.id IS NULL AND t.status != 6 AND tf.is_private = 0
        AND (
            EXISTS (SELECT 1 FROM glpi_tickets_users tu WHERE tu.tickets_id = t.id AND tu.users_id = $users_id)
            OR EXISTS (SELECT 1 FROM glpi_groups_tickets gt INNER JOIN glpi_groups_users gu ON gt.groups_id = gu.groups_id WHERE gt.tickets_id = t.id AND gu.users_id = $users_id)
        )
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
        WHERE v.id IS NULL AND t.status IN (1, 2, 3, 4)
        AND (
            EXISTS (SELECT 1 FROM glpi_tickets_users tu WHERE tu.tickets_id = t.id AND tu.users_id = $users_id)
            OR EXISTS (SELECT 1 FROM glpi_groups_tickets gt INNER JOIN glpi_groups_users gu ON gt.groups_id = gu.groups_id WHERE gt.tickets_id = t.id AND gu.users_id = $users_id)
        )
    )";

    // Chamados Recusados
    $union_parts[] = "(
        SELECT t.id AS ticket_id, CAST(its.id AS CHAR) AS followup_id
        FROM glpi_tickets t
        INNER JOIN (SELECT items_id, MAX(id) as latest_id FROM glpi_itilsolutions WHERE status = 4 AND itemtype = 'Ticket' GROUP BY items_id) latest ON t.id = latest.items_id
        INNER JOIN glpi_itilsolutions its ON its.id = latest.latest_id
        LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.followup_id = CAST(its.id AS CHAR))
        WHERE its.users_id_approval <> $users_id AND v.id IS NULL AND t.status != 6
        AND (
            EXISTS (SELECT 1 FROM glpi_tickets_users tu WHERE tu.tickets_id = t.id AND tu.users_id = $users_id)
            OR EXISTS (SELECT 1 FROM glpi_groups_tickets gt INNER JOIN glpi_groups_users gu ON gt.groups_id = gu.groups_id WHERE gt.tickets_id = t.id AND gu.users_id = $users_id)
        )
    )";

    // Validações (Como validador)
    $union_parts[] = "(
        SELECT t.id AS ticket_id, CAST(tv.id AS CHAR) AS followup_id
        FROM glpi_ticketvalidations tv
        INNER JOIN glpi_tickets t ON tv.tickets_id = t.id AND t.status != 6
        LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.ticket_id = t.id AND v.followup_id = CAST(tv.id AS CHAR))
        WHERE tv.status = 2 AND v.id IS NULL AND tv.users_id_validate = $users_id
        AND tv.submission_date > DATE_SUB(NOW(), INTERVAL 90 DAY)
    )";

    // Respostas de Validação
    $union_parts[] = "(
        SELECT t.id AS ticket_id, CONCAT('validation_response_', tv.id) AS followup_id
        FROM glpi_tickets t
        INNER JOIN glpi_ticketvalidations tv ON t.id = tv.tickets_id AND tv.users_id = $users_id
        LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.ticket_id = t.id AND v.followup_id = CONCAT('validation_response_', tv.id))
        WHERE t.status != 6 AND (tv.status = 3 OR tv.status = 4) AND v.id IS NULL AND tv.validation_date > DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND (
            EXISTS (SELECT 1 FROM glpi_tickets_users tu WHERE tu.tickets_id = t.id AND tu.users_id = $users_id)
            OR EXISTS (SELECT 1 FROM glpi_groups_tickets gt INNER JOIN glpi_groups_users gu ON gt.groups_id = gu.groups_id WHERE gt.tickets_id = t.id AND gu.users_id = $users_id)
        )
    )";

    // Mudanças de Status
    $union_parts[] = "(
        SELECT t.id AS ticket_id, CONCAT('status_', t.id, '_', t.status) AS followup_id
        FROM glpi_tickets t
        LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.ticket_id = t.id AND (v.followup_id = CONCAT('status_', t.id, '_', t.status) OR v.followup_id = CONCAT('status_', t.id, '_any')))
        WHERE v.id IS NULL AND t.status IN (2, 3, 4, 5) AND t.date_mod > DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND (
            EXISTS (SELECT 1 FROM glpi_tickets_users tu WHERE tu.tickets_id = t.id AND tu.users_id = $users_id)
            OR EXISTS (SELECT 1 FROM glpi_groups_tickets gt INNER JOIN glpi_groups_users gu ON gt.groups_id = gu.groups_id WHERE gt.tickets_id = t.id AND gu.users_id = $users_id)
        )
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
            AND (
                EXISTS (SELECT 1 FROM glpi_tickets_users tu WHERE tu.tickets_id = t.id AND tu.users_id = $users_id)
                OR EXISTS (SELECT 1 FROM glpi_groups_tickets gt INNER JOIN glpi_groups_users gu ON gt.groups_id = gu.groups_id WHERE gt.tickets_id = t.id AND gu.users_id = $users_id)
            )
        )";
    }

    if (!empty($union_parts)) {
        $subquery = implode(" UNION ", $union_parts);

        // Obter contagem exata de notificações antes da inserção
        $count_sql = "SELECT COUNT(*) AS total FROM (SELECT DISTINCT ticket_id, followup_id FROM ($subquery) AS temp) AS total_temp";
        $count_result = $DB->query($count_sql);
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

            $result = $DB->query($bulk_insert_query);
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
