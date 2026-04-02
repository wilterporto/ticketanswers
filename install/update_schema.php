<?php
// Não incluir includes.php aqui, pois será chamado pelo install.php

// Adicionar coluna ticket_id se não existir
if (!$DB->fieldExists('glpi_plugin_ticketanswers_views', 'ticket_id')) {
    $DB->query("ALTER TABLE `glpi_plugin_ticketanswers_views` 
                ADD COLUMN `ticket_id` VARCHAR(255) NOT NULL `users_id`,
                ADD INDEX `ticket_id` (`ticket_id`)");
    
    // Log da alteração
    error_log("Adicionada coluna ticket_id à tabela glpi_plugin_ticketanswers_views");
}

// Modificar a coluna followup_id para permitir NULL se necessário
$query = "SHOW COLUMNS FROM `glpi_plugin_ticketanswers_views` LIKE 'followup_id'";
$result = $DB->query($query);
if ($result && $DB->numrows($result) > 0) {
    $column_info = $DB->fetchAssoc($result);
    // Verificar se a coluna já permite NULL
    if (strpos(strtoupper($column_info['Null']), 'NO') !== false) {
        $DB->query("ALTER TABLE `glpi_plugin_ticketanswers_views` 
                    MODIFY COLUMN `followup_id` VARCHAR(255) NOT NULL");
        
        // Log da alteração
        error_log("Modificada coluna followup_id para permitir NULL");
    }
}

// Adicionar coluna message_id se não existir
if (!$DB->fieldExists('glpi_plugin_ticketanswers_views', 'message_id')) {
    $DB->query("ALTER TABLE `glpi_plugin_ticketanswers_views` 
                ADD COLUMN `message_id` VARCHAR(255) DEFAULT NULL `followup_id`");
    
    // Log da alteração
    error_log("Adicionada coluna message_id à tabela glpi_plugin_ticketanswers_views");
}

// Garantir que os tipos de dados estejam corretos
$DB->query("ALTER TABLE `glpi_plugin_ticketanswers_views` 
            MODIFY `ticket_id` VARCHAR(255) NOT NULL,
            MODIFY `followup_id` VARCHAR(255) NOT NULL");

// Log da alteração
error_log("Atualizados tipos de dados para BIGINT");

// Criar a tabela de preferências de notificação se não existir (para usuários com o plugin já instalado)
if (!$DB->tableExists('glpi_plugin_ticketanswers_notification_prefs')) {
    $query = "CREATE TABLE `glpi_plugin_ticketanswers_notification_prefs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `users_id` int(11) NOT NULL,
        `followup` tinyint(1) NOT NULL DEFAULT 1,
        `refused` tinyint(1) NOT NULL DEFAULT 1,
        `group_tickets` tinyint(1) NOT NULL DEFAULT 1,
        `observer` tinyint(1) NOT NULL DEFAULT 1,
        `group_observer` tinyint(1) NOT NULL DEFAULT 1,
        `assigned_tech` tinyint(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`),
        UNIQUE KEY `users_id` (`users_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $DB->query($query);
    
    if ($DB->error()) {
        error_log("Erro ao criar tabela glpi_plugin_ticketanswers_notification_prefs durante atualização: " . $DB->error());
    } else {
        error_log("Tabela glpi_plugin_ticketanswers_notification_prefs criada com sucesso durante atualização");
    }
}
