<?php
include ("../../../inc/includes.php");

Session::checkLoginUser();

Html::header("Ticket Answers", $_SERVER['PHP_SELF'], "plugins", "ticketanswers");

// Carregar CSS primeiro
echo Html::css("/plugins/ticketanswers/css/ticketanswers.css");

echo Html::css("/plugins/ticketanswers/css/vol_icone_notification.css");

// Obter configurações
$config = Config::getConfigurationValues('plugin:ticketanswers');
$check_interval = $config['check_interval'] ?? 300;
$enable_sound = $config['enable_sound'] ?? 1;
$sound_volume = $config['sound_volume'] ?? 70;

// Verificar se há um valor selecionado pelo usuário
$notifications_per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : ($config['notifications_per_page'] ?? 30);

// Garantir que o valor seja um dos permitidos (adicionado 20)
$allowed_values = [10, 20, 30, 50, 100];
if (!in_array($notifications_per_page, $allowed_values)) {
    $notifications_per_page = ($config['notifications_per_page'] ?? 30); // Usar valor da configuração por padrão
}

// Capturar página atual para paginação
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset_value = ($current_page - 1) * $notifications_per_page;

// Configurações agora carregadas globalmente via js/config_loader.php
// (Linhas 33-39 removidas)

// Carregar o script unificado de notificações
echo Html::script("/plugins/ticketanswers/js/unified_notifications.js");

// Adicionar as funções JavaScript necessárias diretamente no arquivo
echo "<script>
// Função para marcar todas as notificações como lidas
function markAllAsRead() {
    if (confirm('" . __("Deseja realmente marcar todas as notificações como lidas?", "ticketanswers") . "')) {
        $.ajax({
            url: CFG_GLPI.root_doc + '/plugins/ticketanswers/ajax/mark_all_as_read.php',
            type: 'GET',
            data: {
                ajax: 1
            },
            success: function(response) {
                // Recarregar a página após marcar todas como lidas
                window.location.reload();
            }
        });
    }
}


// Função para marcar uma notificação como lida
function markNotificationAsRead(ticketId, followupId, type, newTab) {
    // Adicionar logs para depuração
    console.log('Iniciando markNotificationAsRead:', {ticketId, followupId, type, newTab});
    
    // Gerar um message_id único baseado no timestamp atual
    var messageId = 'notification_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
    
    // Fazer a requisição AJAX para marcar como lido
    $.ajax({
        url: CFG_GLPI.root_doc + '/plugins/ticketanswers/ajax/mark_notification_as_read.php',
        type: 'GET',
        data: {
            ticket_id: ticketId,
            followup_id: followupId,
            type: type,
            message_id: messageId,
            ajax: 1
        },
        success: function(response) {
            console.log('Resposta do servidor:', response);
            
            // Determinar o ID da linha com base no tipo
            let rowId;
            
            // Para notificações de status, o ID pode estar em um formato específico
            if (type === 'status_change') {
                // Tente encontrar a linha usando diferentes estratégias
                let statusRow = $('tr[data-notification-type=\"status_change\"][data-ticket-id=\"' + ticketId + '\"]');
                
                if (statusRow.length > 0) {
                    // Se encontrou pelo atributo data, use essa linha diretamente
                    statusRow.fadeOut(500, function() {
                        $(this).remove();
                        checkForEmptyTable();
                    });
                    
                    handleNavigation();
                    return; // Saia da função após lidar com a remoção
                }
                
                // Se não encontrou, tente com o ID específico
                rowId = 'notification-row-' + ticketId + '-' + followupId;
            } else if (type === 'followup' ||
                type === 'refused' ||
                type === 'validation' ||
                type === 'validation_request' ||
                type === 'validation_approved' ||
                type === 'validation_refused' ||
                type === 'validation_response' ||
                type === 'validation_request_response' ||
                type === 'pending_reason' ||
                type === 'technician_response') {
                rowId = 'notification-row-' + ticketId + '-' + followupId;
            } else {
                rowId = 'group-notification-row-' + ticketId;
            }
            
            console.log('ID da linha a ser removida:', rowId);
            
            // Animar a remoção da linha da tabela
            $('#' + rowId).fadeOut(500, function() {
                $(this).remove();
                checkForEmptyTable();
            });
            
            handleNavigation();
            
            // Função auxiliar para verificar se a tabela ficou vazia
            function checkForEmptyTable() {
                if ($('table.tab_cadre_fixehov tr').length <= 1) {
                    // Se só sobrou o cabeçalho, mostrar mensagem de 'não há notificações'
                    $('table.tab_cadre_fixehov').replaceWith(
                    \"<div class='alert alert-info'>" . __("Não há novas notificações", "ticketanswers") . "</div>\"
                    );
                }
                
                // Atualizar o contador de notificações de forma precisa
                if (typeof window.NotificationBell !== 'undefined') {
                    window.NotificationBell.updateBellCount();
                }
            }
            
            // Função auxiliar para lidar com a navegação
            function handleNavigation() {
                // Comportamento diferente baseado no parâmetro newTab
                if (newTab) {
                    // Modificado para usar diretamente o objeto CFG_GLPI
                    var ticketUrl = CFG_GLPI.root_doc + '/front/ticket.form.php?id=' + ticketId;
                    console.log('Abrindo nova aba com URL:', ticketUrl);
                    
                    // Criar um elemento <a> temporário
                    var tempLink = document.createElement('a');
                    tempLink.href = ticketUrl;
                    tempLink.target = '_blank';
                    tempLink.rel = 'noopener noreferrer'; // Por segurança
                    tempLink.style.display = 'none';
                    
                    // Adicionar ao documento, clicar e remover
                    document.body.appendChild(tempLink);
                    tempLink.click();
                    document.body.removeChild(tempLink);
                    
                    // Atualizar a lista de notificações na página atual
                    refreshNotificationsList();
                } else {
                    // Modificado para usar diretamente o objeto CFG_GLPI
                    var redirectUrl = CFG_GLPI.root_doc + '/front/ticket.form.php?id=' + ticketId;
                    console.log('Redirecionando para:', redirectUrl);
                    
                    // Forçar o redirecionamento com setTimeout para garantir execução
                    setTimeout(function() {
                        window.location.href = redirectUrl;
                    }, 100);
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('Erro na requisição AJAX:', error);
            console.error('Status:', status);
            console.error('Resposta:', xhr.responseText);
            alert('Erro ao processar a notificação: ' + error);
        }
    });
}




// Função para atualizar o contador de notificações
function updateNotificationCount() {
    // Contar quantas linhas de notificação ainda existem (excluindo o cabeçalho)
    var count = 0;
    $('table.tab_cadre_fixehov').each(function() {
        count += $(this).find('tr').length - 1;
    });
    
    if (count < 0) count = 0;
    
    // Atualizar o contador na página
    $('#notification-count').text(count);
}

// Função para atualizar a lista de notificações
function refreshNotificationsList() {
    // Preservar a seleção atual de itens por página e página atual
    var currentPerPage = $('#per_page').val() || $('#per_page_bottom').val() || 30;
    var currentPage = new URLSearchParams(window.location.search).get('page') || 1;
    
    $.ajax({
        url: window.location.pathname,
        type: 'GET',
        data: {
            ajax: 1,
            per_page: currentPerPage,
            page: currentPage
        },
        success: function(html) {
            // Extrair apenas o conteúdo do container de notificações
            var tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            var newContent = $(tempDiv).find('#notifications-container').html();
            
            // Atualizar o conteúdo
            $('#notifications-container').html(newContent);
            
            // Atualizar o contador
            var count = $(tempDiv).find('#notification-count').text();
            $('#notification-count').text(count);
        }
    });
}

// Função para atualizar o contador de notificações
function updateNotificationCount() {
    // Contar quantas linhas de notificação ainda existem (excluindo o cabeçalho)
    var count = 0;
    $('table.tab_cadre_fixehov').each(function() {
        count += $(this).find('tr').length - 1;
    });
    
    if (count < 0) count = 0;
    
    // Atualizar o contador na página
    $('#notification-count').text(count);
}


// Função para assumir um chamado
function assignTicketToMe(ticketId) {
    console.log('Assumindo chamado:', ticketId); // Log para depuração
    
    if (!ticketId || ticketId <= 0) {
        console.error('ID do chamado inválido:', ticketId);
        alert('ID do chamado inválido.');
        return;
    }
    
    if (confirm('" . __("Deseja realmente assumir este chamado?", "ticketanswers") . "')) {
        $.ajax({
            url: CFG_GLPI.root_doc + '/plugins/ticketanswers/ajax/assign_ticket.php',
            type: 'POST',
            data: {
                ticket_id: ticketId
            },
            dataType: 'json', // Especificar que esperamos JSON
            success: function(response) {
                console.log('Resposta recebida:', response);
                
                if (response.success) {
                    // Remover a linha da tabela
                    $('#group-notification-row-' + ticketId).fadeOut(500, function() {
                        $(this).remove();
                        
                        // Verificar se ainda há notificações
                        if ($('table.tab_cadre_fixehov tr').length <= 1) {
                            // Se só sobrou o cabeçalho, mostrar mensagem de 'não há notificações'
                            $('table.tab_cadre_fixehov').replaceWith(
                                \"<div class='alert alert-info'>" . __("Não há novas notificações", "ticketanswers") . "</div>\"
                            );
                        }
                    });
                    
                    // Mostrar mensagem de sucesso
                    alert(response.message || '" . __("Chamado assumido com sucesso!", "ticketanswers") . "');
                    
                    // Redirecionar para a página do ticket
                    window.location.href = CFG_GLPI.root_doc + '/front/ticket.form.php?id=' + ticketId;
                } else {
                    alert(response.message || '" . __("Erro ao assumir o chamado.", "ticketanswers") . "');
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro AJAX:', status, error);
                console.log('Resposta:', xhr.responseText);
                alert('" . __("Erro ao comunicar com o servidor:", "ticketanswers") . " ' + error);
            }
        });
    }
}
</script>";

echo "<div class='center'>";

// Obtém o ID do usuário logado
$users_id = Session::getLoginUserID();

echo "<h1>" . __("Ticket Answers", "ticketanswers") . "</h1>";

// Verificar se as tabelas de pendência existem (GLPI 10+ ou Plugin)
$has_pending_tables = $DB->tableExists('glpi_pendingreasons') && $DB->tableExists('glpi_pendingreasons_items');

// Adicionar parte de pendências apenas se as tabelas existirem
$pending_reason_union = "";
if ($has_pending_tables) {
$pending_reason_union = "
UNION
(
    -- Notificações de chamados pendentes com motivo
    SELECT
        t.id AS ticket_id,
        t.name AS ticket_name,
        t.content AS ticket_content,
        t.status AS ticket_status,
        0 AS followup_id,
        pri.last_bump_date AS notification_date,
        CONCAT('Chamado pendente - Motivo: ', pr.name) AS followup_content,
        u.name AS user_name,
        NULL AS group_name,
        NULL AS refuse_reason,
        'pending_reason' AS notification_type
    FROM
        glpi_tickets t
        INNER JOIN glpi_tickets_users tu ON t.id = tu.tickets_id AND tu.type = 1 AND tu.users_id = $users_id
        INNER JOIN glpi_pendingreasons_items pri ON t.id = pri.items_id AND pri.itemtype = 'Ticket'
        INNER JOIN glpi_pendingreasons pr ON pri.pendingreasons_id = pr.id
        LEFT JOIN glpi_users u ON t.users_id_recipient = u.id
        LEFT JOIN glpi_plugin_ticketanswers_views v ON (
            v.users_id = $users_id AND
            v.ticket_id = t.id AND
            v.followup_id = CONCAT('pending_', t.id, '_', pri.pendingreasons_id)
        )
    WHERE
        v.id IS NULL
        AND t.status = 3  -- Pendente
        AND pri.last_bump_date > DATE_SUB(NOW(), INTERVAL 7 DAY)
)";
}

// Consulta base sem LIMIT e sem ORDER BY
$combined_query_base = "(
    -- Notificações de respostas de técnicos em chamados abertos pelo usuário (Técnico respondendo ao Requerente)
    SELECT
        t.id AS ticket_id,
        t.name AS ticket_name,
        t.content AS ticket_content,
        t.status AS ticket_status,
        tf.id AS followup_id,
        tf.date AS notification_date,
        tf.content AS followup_content,
        u.name AS user_name,
        NULL AS group_name,
        NULL AS refuse_reason,
        'technician_response' AS notification_type
    FROM
        glpi_tickets t
        INNER JOIN glpi_tickets_users tu ON t.id = tu.tickets_id AND tu.type = 1 AND tu.users_id = $users_id
        INNER JOIN glpi_itilfollowups tf ON t.id = tf.items_id AND tf.itemtype = 'Ticket'
        LEFT JOIN glpi_users u ON tf.users_id = u.id
        LEFT JOIN glpi_plugin_ticketanswers_views v ON (
            v.users_id = $users_id AND
            v.followup_id = CAST(tf.id AS CHAR)
        )
    WHERE
        tf.users_id <> $users_id
        AND v.id IS NULL
        AND t.status != 6
        AND tf.is_private = 0
        -- Verificar se o autor do acompanhamento é um técnico do chamado
        AND EXISTS (
            SELECT 1 FROM glpi_tickets_users tech_user
            WHERE tech_user.tickets_id = t.id
            AND tech_user.users_id = tf.users_id
            AND tech_user.type = 2
        )
)
UNION
(
    -- Notificações de chamados onde o usuário é observador
    SELECT
        t.id AS ticket_id,
        t.name AS ticket_name,
        t.content AS ticket_content,
        t.status AS ticket_status,
        t.id + 20000000 AS followup_id,
        t.date_mod AS notification_date,
        t.content AS followup_content,
        u_requester.name AS user_name,
        NULL AS group_name,
        NULL AS refuse_reason,
        'observer' AS notification_type
    FROM
        glpi_tickets t
        INNER JOIN glpi_tickets_users tu ON t.id = tu.tickets_id AND tu.type = 3 AND tu.users_id = $users_id
        LEFT JOIN glpi_users u_requester ON t.users_id_recipient = u_requester.id
        LEFT JOIN glpi_plugin_ticketanswers_views v ON (
            v.users_id = $users_id AND
            v.ticket_id = t.id AND
            v.followup_id = CAST(t.id + 20000000 AS CHAR)
        )
    WHERE
        v.id IS NULL
        AND t.status IN (1, 2, 3, 4)
        -- Não mostrar se já é requerente ou técnico (observador puro)
        AND NOT EXISTS (
            SELECT 1 FROM glpi_tickets_users requester
            WHERE requester.tickets_id = t.id AND requester.users_id = $users_id AND requester.type = 1
        )
        AND NOT EXISTS (
            SELECT 1 FROM glpi_tickets_users technician
            WHERE technician.tickets_id = t.id AND technician.users_id = $users_id AND technician.type = 2
        )
)
UNION
(
    -- Notificações de chamados onde o grupo do usuário é observador
    SELECT
        t.id AS ticket_id,
        t.name AS ticket_name,
        t.content AS ticket_content,
        t.status AS ticket_status,
        t.id + 20000000 AS followup_id,
        tf.date AS notification_date,
        tf.content AS followup_content,
        u.name AS user_name,
        g.name AS group_name,
        NULL AS refuse_reason,
        'group_observer' AS notification_type
    FROM
        glpi_tickets t
        INNER JOIN glpi_groups_tickets gt ON t.id = gt.tickets_id AND gt.type = 3
        INNER JOIN glpi_groups_users gu ON gt.groups_id = gu.groups_id AND gu.users_id = $users_id
        INNER JOIN glpi_itilfollowups tf ON t.id = tf.items_id AND tf.itemtype = 'Ticket'
        LEFT JOIN glpi_users u ON tf.users_id = u.id
        LEFT JOIN glpi_groups g ON gt.groups_id = g.id
        LEFT JOIN glpi_plugin_ticketanswers_views v ON (
            v.users_id = $users_id AND
            v.ticket_id = t.id AND
            v.followup_id = CAST(t.id + 20000000 AS CHAR)
        )
    WHERE
        v.id IS NULL
        AND t.status != 6
        AND tf.date > (
            SELECT
                COALESCE(MAX(date), '1970-01-01')
            FROM
                glpi_itilfollowups tf2
            WHERE
                tf2.items_id = t.id
                AND tf2.itemtype = 'Ticket'
                AND tf2.users_id = $users_id
        )
)
UNION
(
    -- Notificações de chamados recusados
    SELECT
        t.id AS ticket_id,
        t.name AS ticket_name,
        t.content AS ticket_content,
        t.status AS ticket_status,
        its.id AS followup_id,
        its.date_approval AS notification_date,
        its.content AS followup_content,
        u.realname AS user_name,
        NULL AS group_name,
        tf.content AS refuse_reason,
        'refused' AS notification_type
    FROM
        glpi_tickets t
        INNER JOIN glpi_tickets_users tu ON t.id = tu.tickets_id AND tu.users_id = $users_id
        INNER JOIN (
            SELECT items_id, MAX(id) as latest_solution_id
            FROM glpi_itilsolutions
            WHERE status = 4 AND itemtype = 'Ticket'
            GROUP BY items_id
        ) latest ON t.id = latest.items_id
        INNER JOIN glpi_itilsolutions its ON its.id = latest.latest_solution_id
        LEFT JOIN glpi_users u ON its.users_id_approval = u.id
        LEFT JOIN glpi_itilfollowups tf ON (
            tf.items_id = t.id 
            AND tf.itemtype = 'Ticket'
            AND tf.users_id = its.users_id_approval
            AND tf.date = its.date_approval
        )
        LEFT JOIN glpi_plugin_ticketanswers_views v ON (
            v.users_id = $users_id AND
            v.followup_id = CAST(its.id AS CHAR)
        )
    WHERE
        its.users_id_approval <> $users_id
        AND v.id IS NULL
        AND t.status != 6
)
UNION
(
    -- Consulta otimizada para todas as validações (como validador ou solicitante)
    SELECT DISTINCT
        t.id AS ticket_id,
        t.name AS ticket_name,
        t.content AS ticket_content,
        t.status AS ticket_status,
        tv.id AS followup_id,
        tv.submission_date AS notification_date,
        CONCAT('Solicitação de validação: ', IF(tv.comment_submission IS NOT NULL AND tv.comment_submission != '', 
               tv.comment_submission, 'Sem comentários adicionais.')) AS followup_content,
        CASE
            WHEN tv.users_id_validate = $users_id THEN u_requester.name
            ELSE u_validator.name
        END AS user_name,
        NULL AS group_name,
        NULL AS refuse_reason,
        CASE
            WHEN tv.users_id_validate = $users_id THEN 'validation'
            ELSE 'validation_request'
        END AS notification_type
    FROM
        glpi_ticketvalidations tv
        INNER JOIN glpi_tickets t ON tv.tickets_id = t.id AND t.status != 6
        LEFT JOIN glpi_users u_requester ON tv.users_id = u_requester.id
        LEFT JOIN glpi_users u_validator ON tv.users_id_validate = u_validator.id
        LEFT JOIN glpi_plugin_ticketanswers_views v ON (
            v.users_id = $users_id AND
            v.ticket_id = t.id AND
            v.followup_id = CAST(tv.id AS CHAR)
        )
    WHERE
        tv.status = 2 -- Aguardando validação
        AND v.id IS NULL
        AND (
            tv.users_id_validate = $users_id
            OR EXISTS (
                SELECT 1 FROM glpi_tickets_users tu
                WHERE tu.tickets_id = t.id AND tu.users_id = $users_id AND tu.type = 1
            )
        )
        AND tv.submission_date > DATE_SUB(NOW(), INTERVAL 90 DAY)
)
UNION
(
    -- Respondendo a solicitações de validação para Requerentes
    SELECT DISTINCT
        t.id AS ticket_id,
        t.name AS ticket_name,
        t.content AS ticket_content,
        t.status AS ticket_status,
        CONCAT('validation_response_', tv.id) AS followup_id,
        tv.validation_date AS notification_date,
        CASE
            WHEN tv.status = 3 THEN CONCAT('Sua solicitação de validação foi APROVADA', 
                                         IF(tv.comment_validation IS NOT NULL AND tv.comment_validation != '',
                                            CONCAT(': ', tv.comment_validation), 
                                            ': Sem comentários adicionais.'))
            WHEN tv.status = 4 THEN CONCAT('Sua solicitação de validação foi RECUSADA', 
                                         IF(tv.comment_validation IS NOT NULL AND tv.comment_validation != '',
                                            CONCAT(': ', tv.comment_validation), 
                                            ': Sem comentários de recusa.'))
            ELSE 'Validação respondida.'
        END AS followup_content,
        u_validator.name AS user_name,
        NULL AS group_name,
        tv.comment_validation AS refuse_reason,
        CASE
            WHEN tv.status = 3 THEN 'validation_approved'
            WHEN tv.status = 4 THEN 'validation_refused'
            ELSE 'validation_response'
        END AS notification_type
    FROM
        glpi_tickets t
        INNER JOIN glpi_tickets_users tu ON t.id = tu.tickets_id AND tu.users_id = $users_id AND tu.type = 1
        INNER JOIN glpi_ticketvalidations tv ON t.id = tv.tickets_id AND tv.users_id = $users_id
        LEFT JOIN glpi_users u_validator ON tv.users_id_validate = u_validator.id
        LEFT JOIN glpi_plugin_ticketanswers_views v ON (
            v.users_id = $users_id AND
            v.ticket_id = t.id AND
            v.followup_id = CONCAT('validation_response_', tv.id)
        )
    WHERE
        t.status != 6
        AND (tv.status = 3 OR tv.status = 4)
        AND v.id IS NULL
        AND tv.validation_date > DATE_SUB(NOW(), INTERVAL 30 DAY)
)
UNION
(
    -- Notificações de mudanças de status em chamados do usuário
    SELECT
        t.id AS ticket_id,
        t.name AS ticket_name,
        t.content AS ticket_content,
        t.status AS ticket_status,
        CONCAT('status_', t.id, '_', t.status) AS followup_id,
        t.date_mod AS notification_date,
        CONCAT('Status alterado para: ',
               CASE
                  WHEN t.status = 1 THEN 'Novo'
                  WHEN t.status = 2 THEN 'Em atendimento'
                  WHEN t.status = 4 THEN 'Pendente'
                  WHEN t.status = 5 THEN 'Solucionado'
                  WHEN t.status = 6 THEN 'Fechado'
                  ELSE 'Outro status'
               END) AS followup_content,
        NULL AS user_name,
        NULL AS group_name,
        NULL AS refuse_reason,
        'status_change' AS notification_type
    FROM
        glpi_tickets t
        INNER JOIN glpi_tickets_users tu ON t.id = tu.tickets_id AND tu.type = 1 AND tu.users_id = $users_id
        LEFT JOIN glpi_plugin_ticketanswers_views v ON (
            v.users_id = $users_id AND
            v.ticket_id = t.id AND
            (
                v.followup_id = CONCAT('status_', t.id, '_', t.status) OR
                v.followup_id = CONCAT('status_', t.id, '_any')
            )
        )
    WHERE
        v.id IS NULL
        AND t.status IN (2, 3, 4, 5)
        AND t.date_mod > DATE_SUB(NOW(), INTERVAL 7 DAY)
    )
";

$combined_query_base .= $pending_reason_union;

// Consulta para contar o total de notificações únicas por chamado (sem LIMIT)
$count_query = "
SELECT COUNT(*) as total FROM (
    SELECT ticket_id
    FROM ($combined_query_base) as inner_count_query
    GROUP BY ticket_id
) as unique_notifications";

// Executar a consulta de contagem
$count_result = $DB->query($count_query);
$total_notifications = 0;
if ($count_result && $DB->numrows($count_result) > 0) {
    $total_data = $DB->fetchAssoc($count_result);
    $total_notifications = $total_data['total'];
}

// Calcular total de páginas
$total_pages = ceil($total_notifications / $notifications_per_page);
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
    $offset_value = ($current_page - 1) * $notifications_per_page;
}

// Consulta principal simplificada com subconsulta para garantir a exclusividade por chamado (o mais recente)
$combined_query = "
SELECT outer_query.*
FROM ($combined_query_base) AS outer_query
INNER JOIN (
    -- Subconsulta para pegar apenas a notificação mais recente por ticket
    SELECT ticket_id, MAX(notification_date) as latest_date
    FROM ($combined_query_base) as inner_base
    GROUP BY ticket_id
) as latest_filter ON 
    outer_query.ticket_id = latest_filter.ticket_id AND 
    outer_query.notification_date = latest_filter.latest_date
GROUP BY outer_query.ticket_id
ORDER BY outer_query.notification_date DESC
LIMIT $notifications_per_page OFFSET $offset_value";

// Executar a consulta principal
$result = $DB->query($combined_query);
$numNotifications = $DB->numrows($result);

// Log para depuração
error_log("DEBUG: notifications_per_page=$notifications_per_page, total_notifications=$total_notifications, numNotifications=$numNotifications");

echo "<div class='center' id='ticket-notifications-wrapper'>";
echo "<div id='ticket-notifications' class='card shadow-sm'>";
echo "<h2 class='card-header'>" . __("Notificações", "ticketanswers") . "</h2>";
echo "<script>
document.addEventListener('DOMContentLoaded', function() {
    // Buscar a contagem atual das notificações via AJAX
    $.ajax({
        url: CFG_GLPI.root_doc + '/plugins/ticketanswers/ajax/check_all_notifications.php',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            // Verificar se temos dados válidos
            if (data && (data.count !== undefined || data.combined_count !== undefined)) {
                // Usar combined_count ou count, o que estiver disponível
                var notificationCount = data.combined_count !== undefined ? data.combined_count : data.count;
                
                // Atualizar o contador na página
                $('#notification-count').text(notificationCount);
                
                // Log para depuração
                console.log('Contador de notificações atualizado:', notificationCount);
            }
        },
        error: function(xhr, status, error) {
            console.error('Erro ao obter contagem de notificações:', error);
        }
    });
});
</script>";
echo "<div id='notifications-container'>";

if ($result && $numNotifications > 0) {
    // Barra de Ferramentas Premium (Marcar tudo + Paginação)
    echo "<div class='ta-toolbar d-flex justify-content-between align-items-center' style='margin-bottom: 20px; padding: 10px; background: #f8f9fa; border-radius: 8px;'>";
    
    // Botão Marcar Tudo
    echo "<div>";
    echo "<a href='javascript:void(0)' onclick='markAllAsRead()' class='btn btn-warning shadow-sm'>
            <i class='fas fa-check-double' style='margin-right: 8px;'></i> " . __("Marcar todos como lido", "ticketanswers") . "
          </a>";
    echo "</div>";
    
    // Seletor de Itens por Página e Paginação
    echo "<div class='d-flex align-items-center'>";
    
    // Seletor de itens por página
    echo "<form method='get' action='' class='form-inline' style='display: flex; align-items: center; margin-right: 20px;'>";
    echo "<label for='per_page' style='margin-right: 10px; font-weight: 500;'>" . __("Exibir:", "ticketanswers") . "</label>";
    echo "<select name='per_page' id='per_page' class='form-control select-compact' onchange='this.form.submit()'>";
    foreach ([10, 20, 30, 50, 100] as $value) {
        $selected = ($value == $notifications_per_page) ? "selected" : "";
        echo "<option value='$value' $selected>$value</option>";
    }
    echo "</select>";
    echo "</form>";

    // Controles de Paginação (Topo)
    if ($total_pages > 1) {
        $prev_page = max(1, $current_page - 1);
        $next_page = min($total_pages, $current_page + 1);
        
        echo "<div class='pagination-controls' style='display: flex; align-items: center; gap: 5px; background: #fff; padding: 5px 10px; border-radius: 20px; border: 1px solid #dee2e6;'>";
        echo "<a href='?page=1&per_page=$notifications_per_page' class='btn btn-light btn-sm " . ($current_page == 1 ? 'disabled' : '') . "' title='" . __("Primeira página", "ticketanswers") . "'><i class='fas fa-angle-double-left'></i></a>";
        echo "<a href='?page=$prev_page&per_page=$notifications_per_page' class='btn btn-light btn-sm " . ($current_page == 1 ? 'disabled' : '') . "' title='" . __("Página anterior", "ticketanswers") . "'><i class='fas fa-angle-left'></i></a>";
        echo "<span style='margin: 0 10px; font-weight: 600; color: #495057;'>" . sprintf(__("Página %d de %d", "ticketanswers"), $current_page, $total_pages) . "</span>";
        echo "<a href='?page=$next_page&per_page=$notifications_per_page' class='btn btn-light btn-sm " . ($current_page == $total_pages ? 'disabled' : '') . "' title='" . __("Próxima página", "ticketanswers") . "'><i class='fas fa-angle-right'></i></a>";
        echo "<a href='?page=$total_pages&per_page=$notifications_per_page' class='btn btn-light btn-sm " . ($current_page == $total_pages ? 'disabled' : '') . "' title='" . __("Última página", "ticketanswers") . "'><i class='fas fa-angle-double-right'></i></a>";
        echo "</div>";
    }
    
    echo "</div>";
    
    echo "</div>"; // Fim da ta-toolbar

    
    echo "<table class='tab_cadre_fixehov'>";
    echo "<tr>";
    echo "<th>" . __("Nº do Chamado", "ticketanswers") . "</th>";
    echo "<th>" . __("Ticket", "ticketanswers") . "</th>";
    echo "<th>" . __("Data", "ticketanswers") . "</th>";
    echo "<th>" . __("Tipo", "ticketanswers") . "</th>";
    echo "<th>" . __("Status", "ticketanswers") . "</th>";
    echo "<th>" . __("Usuário/Grupo", "ticketanswers") . "</th>";
    echo "<th>" . __("Conteúdo", "ticketanswers") . "</th>";
    echo "<th>" . __("Ações", "ticketanswers") . "</th>";
    echo "</tr>";
    
    $i = 0;
    while ($data = $DB->fetchAssoc($result)) {
        $notification_type = $data['notification_type'];
        // Determinar o ID da linha para cada tipo de notificação
$row_id = "";
if ($notification_type == 'followup' || $notification_type == 'refused') {
    // IDs simples para tipos básicos
    $row_id = "notification-row-" . $data['ticket_id'] . "-" . $data['followup_id'];
} elseif (strpos($notification_type, 'validation') !== false) {
    // Para tipos de validação, verificar se o ID já contém "validation_response_"
    if (is_string($data['followup_id']) && preg_match('/^validation_response_(\d+)$/', $data['followup_id'], $matches)) {
        // Extrair apenas o número da validação para o ID da linha
        $row_id = "notification-row-" . $data['ticket_id'] . "-" . $matches[1];
        // Adicionar log para depuração
        error_log("ID de validação convertido: original={$data['followup_id']}, novo={$matches[1]}");
    } else {
        // Usar o ID completo se não corresponder ao padrão
        $row_id = "notification-row-" . $data['ticket_id'] . "-" . $data['followup_id'];
    }
} else {
    // Para outros tipos como group, observer, etc.
    $row_id = "group-notification-row-" . $data['ticket_id'];
}
      
if ($notification_type == 'status_change') {
    echo "<tr id='notification-row-" . $data['ticket_id'] . "-" . $data['followup_id'] . "' 
          data-notification-type='status_change' 
          data-ticket-id='" . $data['ticket_id'] . "' 
          class='tab_bg_" . ($i % 2 + 1) . "'>";
} else {
    echo "<tr id='$row_id' class='tab_bg_" . ($i % 2 + 1) . "'>";
}
        // Nº do Chamado
        echo "<td>" . $data['ticket_id'] . "</td>";

        // Ticket
        echo "<td>" . $data['ticket_name'] . "</td>";
        
        // Data
        echo "<td>" . Html::convDateTime($data['notification_date']) . "</td>";
        
        // Tipo de notificação
echo "<td>";
switch ($notification_type) {
    // Tipos de Resposta/Interação
    case 'followup':
        echo "<span class='badge bg-info text-white'>" . __("Resposta", "ticketanswers") . "</span>";
        break;
    case 'refused':
        echo "<span class='badge bg-danger text-white'>" . __("Recusado", "ticketanswers") . "</span>";
        break;
    
    // Tipos de Atribuição
    case 'group':
        echo "<span class='badge bg-success text-white'>" . __("Novo chamado", "ticketanswers") . "</span>";
        break;
    
    // Tipos de Observador
    case 'observer':
        echo "<span class='badge bg-primary text-white'>" . __("Observador", "ticketanswers") . "</span>";
        break;
    case 'group_observer':
        echo "<span class='badge bg-primary text-white'>" . __("Grupo observador", "ticketanswers") . "</span>";
        break;
    
    // Tipos de Validação
    case 'validation':
        echo "<span class='badge bg-warning text-white'>" . __("Validação", "ticketanswers") . "</span>";
        break;
    case 'validation_request':
        echo "<span class='badge bg-info text-white'>" . __("Solicitação de Validação", "ticketanswers") . "</span>";
        break;
    case 'validation_request_response':
        // Verificar se a validação foi aprovada ou recusada
        $validation_status = isset($data['validation_status']) ? $data['validation_status'] : 0;
        if ($validation_status == 3) { // Aprovado
            echo "<span class='badge bg-success text-white'>" . __("Validação Aprovada", "ticketanswers") . "</span>";
        } else if ($validation_status == 4) { // Recusado
            echo "<span class='badge bg-danger text-white'>" . __("Validação Recusada", "ticketanswers") . "</span>";
        } else {
            echo "<span class='badge bg-secondary text-white'>" . __("Resp. Validação", "ticketanswers") . "</span>";
        }
        break;
    case 'validation_response':
        // Verificar se a validação foi aprovada ou recusada
        $validation_status = isset($data['validation_status']) ? $data['validation_status'] : 0;
        if ($validation_status == 3) { // Aprovado
            echo "<span class='badge bg-success text-white'>" . __("Validação Aprovada", "ticketanswers") . "</span>";
        } else if ($validation_status == 4) { // Recusado
            echo "<span class='badge bg-danger text-white'>" . __("Validação Recusada", "ticketanswers") . "</span>";
        } else {
            echo "<span class='badge bg-secondary text-white'>" . __("Resp. Validação", "ticketanswers") . "</span>";
        }
        break;
    case 'validation_approved':
        echo "<span class='badge bg-success text-white'>" . __("Validação Aprovada", "ticketanswers") . "</span>";
        break;
    case 'validation_refused':
        echo "<span class='badge bg-danger text-white'>" . __("Validação Recusada", "ticketanswers") . "</span>";
        break;
    
     // Adicionar os novos tipos de notificação
     case 'technician_response':
        echo "<span class='badge bg-secondary text-white'>" . __("Resposta técnica", "ticketanswers") . "</span>";
        break;
    case 'status_change':
        echo "<span class='badge bg-primary text-white'>" . __("Status do chamado", "ticketanswers") . "</span>";
        break;
    case 'pending_reason':
        echo "<span class='badge bg-warning text-white'>" . __("Pendente", "ticketanswers") . "</span>";
        break;
        default:
            echo "<span class='badge bg-secondary text-white'>" . __("Outro", "ticketanswers") . "</span>";
    }
    echo "</td>";

    // Status do chamado
    echo "<td>" . Ticket::getStatus($data['ticket_status']) . "</td>";

            
// Usuário/Grupo
echo "<td>";
if ($notification_type == 'followup') {
    echo $data['user_name']; // Nome do usuário que respondeu
} elseif ($notification_type == 'group' || $notification_type == 'group_observer') {
    echo $data['user_name'] . " <small>(" . __("para grupo", "ticketanswers") . ": " . $data['group_name'] . ")</small>";
} elseif ($notification_type == 'refused') {
    echo $data['user_name'] . " <small>(" . __("recusou o chamado", "ticketanswers") . ")</small>";
} elseif ($notification_type == 'validation' || $notification_type == 'validation_request') {
    echo $data['user_name'] . " <small>(" . __("solicitou validação", "ticketanswers") . ")</small>";
} elseif ($notification_type == 'validation_approved' || 
         ($notification_type == 'validation_request_response' && isset($data['validation_status']) && $data['validation_status'] == 3) ||
         ($notification_type == 'validation_response' && isset($data['validation_status']) && $data['validation_status'] == 3)) {
    echo $data['user_name'] . " <small>(" . __("aprovou a validação", "ticketanswers") . ")</small>";
} elseif ($notification_type == 'validation_refused' || 
         ($notification_type == 'validation_request_response' && isset($data['validation_status']) && $data['validation_status'] == 4) ||
         ($notification_type == 'validation_response' && isset($data['validation_status']) && $data['validation_status'] == 4)) {
    echo $data['user_name'] . " <small>(" . __("recusou a validação", "ticketanswers") . ")</small>";
} elseif ($notification_type == 'validation_request_response') {
    echo $data['user_name'] . " <small>(" . __("respondeu sua solicitação de validação", "ticketanswers") . ")</small>";
} elseif ($notification_type == 'validation_response') {
    echo $data['user_name'] . " <small>(" . __("respondeu à validação", "ticketanswers") . ")</small>";
} else {
    echo $data['user_name'];
}
echo "</td>";
    
            // Conteúdo
echo "<td>";
if ($notification_type == 'refused' && !empty($data['refuse_reason'])) {
    // Para chamados recusados, mostrar a razão da recusa
    $refuse_content = $data['refuse_reason'];
    $decoded_content = html_entity_decode($refuse_content);
    $plain_text = preg_replace('/<.*?>/', '', $decoded_content);
    echo "<strong>" . __("Motivo da recusa", "ticketanswers") . ":</strong> ";
    echo Html::resume_text($plain_text, 100);
} elseif ($notification_type == 'followup') {
    // Decodificar entidades HTML
    $followup_content = $data['followup_content'];
    $decoded_content = html_entity_decode($followup_content);
    // Extrair texto entre tags usando regex
    $plain_text = preg_replace('/<.*?>/', '', $decoded_content);
    echo Html::resume_text($plain_text, 100);
} elseif (in_array($notification_type, ['validation', 'validation_request', 
                                        'validation_approved', 'validation_refused', 
                                        'validation_response', 'validation_request_response'])) {
    // Para todos os tipos de validação, usar diretamente o followup_content
    if (!empty($data['followup_content'])) {
        $content = $data['followup_content'];
        $decoded_content = html_entity_decode($content);
        $plain_text = preg_replace('/<.*?>/', '', $decoded_content);
        
        // Determinar o prefixo apropriado baseado no tipo
        if ($notification_type == 'validation' || $notification_type == 'validation_request') {
            echo "<strong>" . __("Solicitação:", "ticketanswers") . "</strong> ";
        } elseif ($notification_type == 'validation_approved') {
            echo "<strong>" . __("Aprovação:", "ticketanswers") . "</strong> ";
        } elseif ($notification_type == 'validation_refused') {
            echo "<strong>" . __("Recusa:", "ticketanswers") . "</strong> ";
        } elseif ($notification_type == 'validation_response' || $notification_type == 'validation_request_response') {
            echo "<strong>" . __("Resposta:", "ticketanswers") . "</strong> ";
        }
        
        echo Html::resume_text($plain_text, 100);
    } else {
        echo __("Sem conteúdo disponível", "ticketanswers");
    }
} else {
    // Para outros tipos, mostrar um resumo do conteúdo do ticket
    $content = !empty($data['followup_content']) ? $data['followup_content'] : $data['ticket_content'];
    $decoded_content = html_entity_decode($content);
    $plain_text = preg_replace('/<.*?>/', '', $decoded_content);
    echo Html::resume_text($plain_text, 100);
}
echo "</td>";
            
            // Ações
            echo "<td class='center'>";
            echo "<div class='btn-group'>";
            switch ($notification_type) {
                case 'followup':
                    // Link para ver na mesma aba - Usar a função unificada
                    echo "<a href='javascript:void(0)' onclick='markNotificationAsRead(" . $data['ticket_id'] . ", \"" . $data['followup_id'] . "\", \"followup\", false)' class='btn btn-info' title='" . __("Ver chamado", "ticketanswers") . "'>
                    <i class='fas fa-eye'></i>
                  </a>";
                    // Link para ver em nova aba
                    echo "<a href='#' onclick='markNotificationAsRead(" . $data['ticket_id'] . ", \"" . $data['followup_id'] . "\", \"followup\", true); return false;' class='btn btn-secondary' title='" . __("Ver em nova aba", "ticketanswers") . "'>
                    <i class='fas fa-external-link-alt'></i>
                  </a>";
                    break;
                
                case 'group':
                    // Link para ver na mesma aba - Usar função unificada
                    echo "<a href='javascript:void(0)' onclick='markNotificationAsRead(" . $data['ticket_id'] . ", \"0\", \"group\", false)' class='btn btn-info' title='" . __("Ver chamado", "ticketanswers") . "'>
                    <i class='fas fa-eye'></i>
                  </a>";
                    
                    // Botão para assumir o chamado
                    echo "<a href='javascript:void(0)' onclick='assignTicketToMe(" . $data['ticket_id'] . ")' class='btn btn-success' title='" . __("Assumir chamado", "ticketanswers") . "'>
                    <i class='fas fa-user-check'></i>
                  </a>";
                    break;
                
                case 'observer':
                case 'group_observer':
                    // Link para ver na mesma aba
                    echo "<a href='javascript:void(0)' onclick='markNotificationAsRead(" . $data['ticket_id'] . ", \"0\", \"" . $notification_type . "\", false)' class='btn btn-info' title='" . __("Ver chamado", "ticketanswers") . "'>
                    <i class='fas fa-eye'></i>
                  </a>";
                    // Link para ver em nova aba - CORREÇÃO
                    echo "<a href='#' onclick='markNotificationAsRead(" . $data['ticket_id'] . ", \"0\", \"" . $notification_type . "\", true); return false;' class='btn btn-secondary' title='" . __("Ver em nova aba", "ticketanswers") . "'>
                    <i class='fas fa-external-link-alt'></i>
                  </a>";
                    break;
                    
                case 'refused':
                    // Link para ver na mesma aba
                    echo "<a href='javascript:void(0)' onclick='markNotificationAsRead(" . $data['ticket_id'] . ", \"" . $data['followup_id'] . "\", \"refused\", false)' class='btn btn-info' title='" . __("Ver chamado", "ticketanswers") . "'>
                    <i class='fas fa-eye'></i>
                  </a>";
                    // Link para ver em nova aba
                    echo "<a href='#' onclick='markNotificationAsRead(" . $data['ticket_id'] . ", \"" . $data['followup_id'] . "\", \"followup\", true); return false;' class='btn btn-secondary' title='" . __("Ver em nova aba", "ticketanswers") . "'>
                    <i class='fas fa-external-link-alt'></i>
                  </a>";
                    break;
                    

                case 'validation':
                    // Link para ver na mesma aba
                    echo "<a href='javascript:void(0)' onclick='markNotificationAsRead(" . $data['ticket_id'] . ", \"" . $data['followup_id'] . "\", \"validation\", false)' class='btn btn-info' title='" . __("Ver chamado", "ticketanswers") . "'>
                    <i class='fas fa-eye'></i>
                  </a>";
                    // Link para ver em nova aba
                    echo "<a href='#' onclick='markNotificationAsRead(" . $data['ticket_id'] . ", \"" . $data['followup_id'] . "\", \"validation\", true); return false;' class='btn btn-secondary' title='" . __("Ver em nova aba", "ticketanswers") . "'>
                    <i class='fas fa-external-link-alt'></i>
                  </a>";
                    break;
                
                case 'validation_request':
                    // Link para ver na mesma aba
                    echo "<a href='javascript:void(0)' onclick='markNotificationAsRead(" . $data['ticket_id'] . ", \"" . $data['followup_id'] . "\", \"validation_request\", false)' class='btn btn-info' title='" . __("Ver chamado", "ticketanswers") . "'>
                    <i class='fas fa-eye'></i>
                  </a>";
                    // Link para ver em nova aba
                    echo "<a href='#' onclick='markNotificationAsRead(" . $data['ticket_id'] . ", \"" . $data['followup_id'] . "\", \"validation_request\", true); return false;' class='btn btn-secondary' title='" . __("Ver em nova aba", "ticketanswers") . "'>
                    <i class='fas fa-external-link-alt'></i>
                  </a>";
                    break;
                case 'validation_approved':
                case 'validation_refused':
                    // Link para ver na mesma aba
                    echo "<a href='javascript:void(0)' onclick='markNotificationAsRead(" . $data['ticket_id'] . ", \"" . $data['followup_id'] . "\", \"" . $notification_type . "\", false)' class='btn btn-info' title='" . __("Ver chamado", "ticketanswers") . "'>
                    <i class='fas fa-eye'></i>
                  </a>";
                    // Link para ver em nova aba
                        echo "<a href='#' onclick='markNotificationAsRead(" . $data['ticket_id'] . ", \"" . $data['followup_id'] . "\", \"" . $notification_type . "\", true); return false;' class='btn btn-secondary' title='" . __("Ver em nova aba", "ticketanswers") . "'>
                        <i class='fas fa-external-link-alt'></i>
                      </a>";
                        break;
                case 'technician_response':
                case 'status_change':
                case 'pending_reason':
                    // Link para ver na mesma aba
                    echo "<a href='javascript:void(0)' onclick='markNotificationAsRead(" . $data['ticket_id'] . ", \"" . $data['followup_id'] . "\", \"" . $notification_type . "\", false)' class='btn btn-info' title='" . __("Ver chamado", "ticketanswers") . "'>
                    <i class='fas fa-eye'></i>
                      </a>";
                    // Link para ver em nova aba
                    echo "<a href='#' onclick='markNotificationAsRead(" . $data['ticket_id'] . ", \"" . $data['followup_id'] . "\", \"" . $notification_type . "\", true); return false;' class='btn btn-secondary' title='" . __("Ver em nova aba", "ticketanswers") . "'>
                    <i class='fas fa-external-link-alt'></i>
                      </a>";
                      break;
            }
            echo "</div>";
            echo "</td>";
            echo "</tr>";
            $i++;
        }
        
        echo "</table>";
    
    // Rodapé com info de paginação e controles
    echo "<div class='ta-footer' style='margin-top: 25px; padding-top: 15px; border-top: 1px solid #eee; color: #6c757d; font-size: 0.9em; display: flex; justify-content: space-between; align-items: center;'>";
    
    $start_item = $offset_value + 1;
    $end_item = min($offset_value + $notifications_per_page, $total_notifications);
    echo "<div>" . sprintf(__("Exibindo %d-%d de %d notificações", "ticketanswers"), $start_item, $end_item, $total_notifications) . "</div>";
    
    // Controles de Paginação (Rodapé)
    if ($total_pages > 1) {
        $prev_page = max(1, $current_page - 1);
        $next_page = min($total_pages, $current_page + 1);
        
        echo "<div class='pagination-controls' style='display: flex; align-items: center; gap: 5px;'>";
        echo "<a href='?page=1&per_page=$notifications_per_page' class='btn btn-outline-secondary btn-sm " . ($current_page == 1 ? 'disabled' : '') . "'><i class='fas fa-angle-double-left'></i> " . __("Primeira", "ticketanswers") . "</a>";
        echo "<a href='?page=$prev_page&per_page=$notifications_per_page' class='btn btn-outline-secondary btn-sm " . ($current_page == 1 ? 'disabled' : '') . "'><i class='fas fa-angle-left'></i> " . __("Anterior", "ticketanswers") . "</a>";
        
        echo "<span style='margin: 0 10px; font-weight: 500;'>" . sprintf(__("Página %d de %d", "ticketanswers"), $current_page, $total_pages) . "</span>";
        
        echo "<a href='?page=$next_page&per_page=$notifications_per_page' class='btn btn-outline-secondary btn-sm " . ($current_page == $total_pages ? 'disabled' : '') . "'>" . __("Próxima", "ticketanswers") . " <i class='fas fa-angle-right'></i></a>";
        echo "<a href='?page=$total_pages&per_page=$notifications_per_page' class='btn btn-outline-secondary btn-sm " . ($current_page == $total_pages ? 'disabled' : '') . "'>" . __("Última", "ticketanswers") . " <i class='fas fa-angle-double-right'></i></a>";
        echo "</div>";
    }
    
    echo "<div><i class='fas fa-info-circle'></i> " . __("Clique na linha para visualizar o chamado", "ticketanswers") . "</div>";
    echo "</div>";
    } else {
        echo "<div class='alert alert-info'>" . __("Não há novas notificações", "ticketanswers") . "</div>";
    }
    
    echo "</div>"; // Fim do card-body/container
    echo "</div>"; // Fim do ticket-notifications (card)
    echo "</div>"; // Fim do ticket-notifications-wrapper
    
    echo "<script>
    $(document).ready(function() {
        // Inicializar o sino de notificações
        if (typeof addNotificationBell === 'function') {
            console.log('Inicializando sino de notificações...');
            addNotificationBell();
            addNotificationStyles();
        } else {
            console.error('Função addNotificationBell não encontrada!');
        }
    });
    </script>";

    echo "<script>
    $(document).ready(function() {
        // Bloquear botão direito menu de contexto em toda a página de notificações
        $('#notifications-container, .tab_cadre_fixehov').on('contextmenu', function(e) {
            e.preventDefault();
            return false;
        });
        
        // Interceptar Ctrl+clique especificamente nos links
        $('#notifications-container a, .tab_cadre_fixehov a').on('click', function(e) {
            if (e.ctrlKey || e.metaKey) {
                e.preventDefault();
                e.stopPropagation(); // Impede a propagação do evento
                
                // Mostrar mensagem
                showBlockedMessage();
                
                return false;
            }
        });
        
        // Interceptar mousedown para detectar clique do botão do meio
        $('#notifications-container a, .tab_cadre_fixehov a').on('mousedown', function(e) {
            // Botão do meio = 1, botão direito = 2
            if (e.button === 1 || e.button === 2 || (e.button === 0 && (e.ctrlKey || e.metaKey))) {
                e.preventDefault();
                e.stopPropagation();
                
                // Mostrar mensagem
                showBlockedMessage();
                
                return false;
            }
        });
        
        // Também prevenir o comportamento padrão de arrastar links
        $('#notifications-container a, .tab_cadre_fixehov a').on('dragstart', function(e) {
            e.preventDefault();
            return false;
        });
        
        // Função para mostrar mensagem
        function showBlockedMessage() {
            if (!$('#context-menu-warning').length) {
                $('<div id=\"context-menu-warning\" style=\"position:fixed; bottom:10px; right:10px; background:#ffeeee; padding:10px; border:1px solid #ffcccc; border-radius:5px; z-index:9999;\">Botão direito, botão do meio e Ctrl+clique estão desabilitados nesta página.</div>')
                    .appendTo('body')
                    .delay(3000)
                    .fadeOut(500, function() { $(this).remove(); });
            }
        }
        
        // Adicionar atributo oncontextmenu a todos os links (usando método attr do jQuery)
        $('#notifications-container a, .tab_cadre_fixehov a').attr('oncontextmenu', 'return false');
    });
</script>";

Html::footer();

// Se for uma requisição AJAX, retornar apenas o conteúdo HTML sem o header/footer
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    exit();
}