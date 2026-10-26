<?php
if (!defined('GLPI_ROOT')) {
    include (__DIR__ . "/../../../inc/includes.php");
}

Session::checkLoginUser();

Html::header("Ticket Answers", $_SERVER['PHP_SELF'], "plugins", "PluginTicketanswers");

echo "<div class='center'>";
echo "<h1>" . __("Ticket Answers", "ticketanswers") . "</h1>";
echo "<p>" . __("Bem-vindo ao plugin Ticket Answers", "ticketanswers") . "</p>";
echo "</div>";

Html::footer();
