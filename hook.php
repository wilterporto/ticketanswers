<?php
/**
 * Hook file for Ticket Answers plugin
 */

function plugin_ticketanswers_install() {
    global $DB;
    
    // Criar a tabela de visualizações se não existir
if (!$DB->tableExists('glpi_plugin_ticketanswers_views')) {
   $query = "CREATE TABLE `glpi_plugin_ticketanswers_views` (
       `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
       `ticket_id` VARCHAR(255) NOT NULL,
       `users_id` int(11) unsigned NOT NULL,
       `followup_id` VARCHAR(255) NOT NULL,
       `viewed_at` timestamp NOT NULL,
       `message_id` VARCHAR(255) DEFAULT NULL,
       PRIMARY KEY (`id`),
       UNIQUE KEY `unique_view` (`users_id`, `ticket_id`, `followup_id`)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
   
   $DB->query($query);
   
   if ($DB->error()) {
       error_log("Erro ao criar tabela glpi_plugin_ticketanswers_views: " . $DB->error());
       return false;
   }
}

    
    // Resto do código de instalação...
    
    // Criar a tabela de preferências de notificação se não existir
    if (!$DB->tableExists('glpi_plugin_ticketanswers_notification_prefs')) {
       $query = "CREATE TABLE `glpi_plugin_ticketanswers_notification_prefs` (
           `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
           `users_id` int(11) unsigned NOT NULL,
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
           error_log("Erro ao criar tabela glpi_plugin_ticketanswers_notification_prefs: " . $DB->error());
       }
    }
    
    return true;
}
function plugin_ticketanswers_update($old_version) {
   global $DB;
    
   // Versão 1.1.3: Migrar followup_id e ticket_id para VARCHAR
   if (version_compare($old_version, '1.1.3', '<')) {
       if ($DB->tableExists('glpi_plugin_ticketanswers_views')) {
           $DB->query("ALTER TABLE `glpi_plugin_ticketanswers_views` 
                       MODIFY COLUMN `followup_id` VARCHAR(255) NOT NULL,
                       MODIFY COLUMN `ticket_id` VARCHAR(255) NOT NULL");
           
           error_log("Plugin TicketAnswers: Colunas followup_id e ticket_id migradas para VARCHAR(255)");
       }
   }

   // Versão 1.1.4: Reforçar integridade e Chave Única para evitar reexibição
   if (version_compare($old_version, '1.1.4', '<')) {
       if ($DB->tableExists('glpi_plugin_ticketanswers_views')) {
           // 1. Limpar registros com followup_id inválido (bug de versões anteriores)
           $DB->query("DELETE FROM `glpi_plugin_ticketanswers_views` WHERE `followup_id` = '0' OR `followup_id` = ''");
           
           // 2. Remover duplicatas (mantendo apenas o registro mais recente de cada visualização)
           $DB->query("DELETE v1 FROM `glpi_plugin_ticketanswers_views` v1
                       INNER JOIN `glpi_plugin_ticketanswers_views` v2 
                       ON v1.users_id = v2.users_id 
                       AND v1.ticket_id = v2.ticket_id 
                       AND v1.followup_id = v2.followup_id
                       WHERE v1.id < v2.id");
           
           // 3. Adicionar a UNIQUE KEY se não existir
           // Em vez de usar information_schema, tentamos adicionar e silenciamos se já existir
           try {
               $DB->query("ALTER TABLE `glpi_plugin_ticketanswers_views` 
                           ADD UNIQUE KEY `unique_view` (`users_id`, `ticket_id`, `followup_id`)");
           } catch (Exception $e) { /* Já existe ou erro na query */ }
           
           error_log("Plugin TicketAnswers: Integridade da tabela de visualizações reforçada na v1.1.4");
       }
   }
    
   return true;
}

function plugin_ticketanswers_uninstall() {
   global $DB;
    
   if ($DB->tableExists("glpi_plugin_ticketanswers_views")) {
       $query = "DROP TABLE `glpi_plugin_ticketanswers_views`";
       $DB->query($query) or die("Error dropping glpi_plugin_ticketanswers_views " . $DB->error());
   }
    
   if ($DB->tableExists("glpi_plugin_ticketanswers_notification_prefs")) {
        $query = "DROP TABLE `glpi_plugin_ticketanswers_notification_prefs`";
        $DB->query($query);
    }
    
    return true;
}
