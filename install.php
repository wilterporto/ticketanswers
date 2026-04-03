<?php
/*
  -------------------------------------------------------------------------
  Ticket Answers
  Copyright (C) 2023 by Jeferson Penna Alves
  -------------------------------------------------------------------------
  LICENSE
  This file is part of Ticket Answers.
  Ticket Answers is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.
  Ticket Answers is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.
  You should have received a copy of the GNU General Public License
  along with Ticket Answers. If not, see <http://www.gnu.org/licenses/>.
  --------------------------------------------------------------------------
*/

/**
 * Função de instalação do plugin
 */
function plugin_ticketanswers_install() {
    global $DB;
    
    // Criar tabela de visualizações se não existir
    if (!$DB->tableExists('glpi_plugin_ticketanswers_views')) {
        $query = "CREATE TABLE `glpi_plugin_ticketanswers_views` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `ticket_id` VARCHAR(255) NOT NULL,
            `users_id` int(11) unsigned NOT NULL,
            `followup_id` VARCHAR(255) NOT NULL,
            `viewed_at` timestamp NOT NULL,
            `message_id` VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_view` (`users_id`, `ticket_id`, `followup_id`),
            INDEX `users_id` (`users_id`),
            INDEX `ticket_id` (`ticket_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $DB->query($query) or die("Error creating glpi_plugin_ticketanswers_views table: " . $DB->error());
    }
    
    // Criar a tabela de preferências de notificação se não existir
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
        
        $DB->query($query) or die("Error creating glpi_plugin_ticketanswers_notification_prefs table: " . $DB->error());
    }
    
    // Atualizar esquema se necessário
    include_once(__DIR__ . '/install/update_schema.php');
    
    return true;
}

/**
 * Função de desinstalação do plugin
 */
function plugin_ticketanswers_uninstall() {
    global $DB;
    
    // Remover tabela de visualizações
    if ($DB->tableExists('glpi_plugin_ticketanswers_views')) {
        $query = "DROP TABLE `glpi_plugin_ticketanswers_views`";
        $DB->query($query) or die("Error dropping glpi_plugin_ticketanswers_views table");
    }
    
    // Remover tabela de preferências
    if ($DB->tableExists('glpi_plugin_ticketanswers_notification_prefs')) {
        $query = "DROP TABLE `glpi_plugin_ticketanswers_notification_prefs`";
        $DB->query($query) or die("Error dropping glpi_plugin_ticketanswers_notification_prefs table");
    }
    
    return true;
}
