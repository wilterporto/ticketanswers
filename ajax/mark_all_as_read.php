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

    // 1. Identificar todas as notificações não lidas de todos os tipos
    $union_parts = [];

    // Notificações de acompanhamento (followup) - Requerente
    if ($can_see_own) {
        $union_parts[] = "SELECT t.id AS ticket_id, tf.id AS followup_id, 'followup' AS type
                         FROM glpi_tickets t
                         INNER JOIN glpi_tickets_users tu ON t.id = tu.tickets_id AND tu.type = 1 AND tu.users_id = $users_id
                         INNER JOIN glpi_itilfollowups tf ON t.id = tf.items_id AND tf.itemtype = 'Ticket'
                         LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.followup_id = CAST(tf.id AS CHAR))
                         WHERE tf.users_id <> $users_id AND v.id IS NULL AND t.status != 6 AND tf.is_private = 0";
    }

    // Chamados recusados
    if ($can_see_own) {
        $union_parts[] = "SELECT t.id AS ticket_id, its.id AS followup_id, 'refused' AS type
                         FROM glpi_tickets t
                         INNER JOIN glpi_tickets_users tu ON t.id = tu.tickets_id AND tu.users_id = $users_id
                         INNER JOIN glpi_itilsolutions its ON its.items_id = t.id AND its.itemtype = 'Ticket'
                         LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.followup_id = CAST(its.id AS CHAR))
                         WHERE its.status = 4 AND its.users_id_approval <> $users_id AND v.id IS NULL AND t.status != 6";
    }

    // Observadores (Normal e Grupo)
    if ($can_see_observe) {
        // Individual
        $union_parts[] = "SELECT t.id AS ticket_id, t.id + 20000000 AS followup_id, 'observer' AS type
                         FROM glpi_tickets t
                         INNER JOIN glpi_tickets_users tu ON t.id = tu.tickets_id AND tu.type = 3 AND tu.users_id = $users_id
                         LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.followup_id = CAST(t.id + 20000000 AS CHAR))
                         WHERE v.id IS NULL AND t.status IN (1, 2, 3, 4)";
        // Grupo
        $union_parts[] = "SELECT t.id AS ticket_id, t.id + 20000000 AS followup_id, 'group_observer' AS type
                         FROM glpi_tickets t
                         INNER JOIN glpi_groups_tickets gt ON t.id = gt.tickets_id AND gt.type = 3
                         INNER JOIN glpi_groups_users gu ON gt.groups_id = gu.groups_id AND gu.users_id = $users_id
                         LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.followup_id = CAST(t.id + 20000000 AS CHAR))
                         WHERE v.id IS NULL AND t.status IN (1, 2)";
    }

    // Validações Pendentes (Para o Validador)
    $union_parts[] = "SELECT t.id AS ticket_id, tv.id AS followup_id, 'validation' AS type
                     FROM glpi_tickets t
                     INNER JOIN glpi_ticketvalidations tv ON t.id = tv.tickets_id
                     LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.followup_id = CAST(tv.id AS CHAR))
                     WHERE tv.users_id_validate = $users_id AND tv.status = 2 AND v.id IS NULL AND t.status != 6";

    // Respostas de Validação
    if ($can_see_own || $can_see_assign) {
        $union_parts[] = "SELECT t.id AS ticket_id, CONCAT('validation_response_', tv.id) AS followup_id, 'validation_response' AS type
                         FROM glpi_tickets t
                         INNER JOIN glpi_ticketvalidations tv ON t.id = tv.tickets_id AND tv.users_id = $users_id
                         LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.followup_id = CONCAT('validation_response_', tv.id))
                         WHERE (tv.status = 3 OR tv.status = 4) AND v.id IS NULL AND t.status != 6";
    }

    // Mudanças de Status e Motivos de Pendência
    if ($can_see_own) {
        $union_parts[] = "SELECT t.id AS ticket_id, CONCAT('status_', t.id, '_', t.status) AS followup_id, 'status_change' AS type
                         FROM glpi_tickets t
                         INNER JOIN glpi_tickets_users tu ON t.id = tu.tickets_id AND tu.type = 1 AND tu.users_id = $users_id
                         LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.followup_id = CONCAT('status_', t.id, '_', t.status))
                         WHERE t.status IN (2, 3, 4, 5) AND v.id IS NULL";
                         
        if ($DB->tableExists('glpi_pendingreasons_items')) {
            $union_parts[] = "SELECT t.id AS ticket_id, CONCAT('pending_', t.id, '_', pri.pendingreasons_id) AS followup_id, 'pending_reason' AS type
                             FROM glpi_tickets t
                             INNER JOIN glpi_tickets_users tu ON t.id = tu.tickets_id AND tu.type = 1 AND tu.users_id = $users_id
                             INNER JOIN glpi_pendingreasons_items pri ON t.id = pri.items_id AND pri.itemtype = 'Ticket'
                             LEFT JOIN glpi_plugin_ticketanswers_views v ON (v.users_id = $users_id AND v.followup_id = CONCAT('pending_', t.id, '_', pri.pendingreasons_id))
                             WHERE t.status = 3 AND v.id IS NULL";
        }
    }

    if (empty($union_parts)) {
        throw new Exception("Nenhuma notificação para marcar.");
    }
    
    $notifications_query = implode(" UNION ALL ", $union_parts);
    
    $result = $DB->query($notifications_query);
    $count = 0;
    
    // 2. Para cada notificação, marcar como lida usando a mesma lógica dos botões individuais
    while ($data = $DB->fetchAssoc($result)) {
        $ticket_id = $data['ticket_id'];
        $actual_followup_id = $data['followup_id'];
        $notification_type = $data['type'];
        
        // Verificar se já existe um registro para esta notificação
        $check_query = "SELECT id FROM glpi_plugin_ticketanswers_views
                        WHERE users_id = $users_id
                        AND ticket_id = '$ticket_id'
                        AND followup_id = '$actual_followup_id'";
        $check_result = $DB->query($check_query);
        
        // Se não existir, inserir
        if ($DB->numrows($check_result) == 0) {
            $insert_result = $DB->insert('glpi_plugin_ticketanswers_views', [
                'ticket_id' => $ticket_id,
                'users_id' => $users_id,
                'followup_id' => $actual_followup_id,
                'viewed_at' => date('Y-m-d H:i:s'),
                'message_id' => $batch_message_id
            ]);
            
            if ($insert_result) {
                $count++;
            }
        }
    }
    
    $total_count = $count;
    $success = ($count > 0);
    
    error_log("Marcadas $count notificações como lidas no total (message_id: $batch_message_id)");
    
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
?>
