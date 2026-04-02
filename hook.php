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

   // Se estiver atualizando de uma versão anterior à 1.1.1
   if (version_compare($old_version, '1.1.1', '<')) {
       // Verificar se a coluna existe e alterar seu tipo
       if ($DB->fieldExists('glpi_plugin_ticketanswers_views', 'message_id')) {
           $DB->query("ALTER TABLE `glpi_plugin_ticketanswers_views` 
                       MODIFY COLUMN `message_id` VARCHAR(20) DEFAULT NULL");
            
           // Registrar a alteração no log
           error_log("Plugin TicketAnswers: Coluna message_id alterada para VARCHAR(20)");
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
