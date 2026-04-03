<?php
// Configura o tipo de conteúdo como JavaScript
header("Content-Type: application/javascript");

// Inclui o ambiente do GLPI
// O caminho depende de onde este arquivo está. Como está em /plugins/ticketanswers/js/
include ("../../../inc/includes.php");

// Verifica se o usuário está logado
if (!Session::getLoginUserID()) {
    echo "// Usuário não autenticado";
    exit;
}

// Busca as configurações do plugin salvas na tabela glpi_configs
$config = Config::getConfigurationValues('plugin:ticketanswers');

// Define as configurações globais para o JavaScript do plugin
$js_config = [
    'checkInterval'      => intval($config['check_interval'] ?? 300),
    'enableSound'        => isset($config['enable_sound']) ? (bool)$config['enable_sound'] : true,
    'soundVolume'        => intval($config['sound_volume'] ?? 70),
    'showBellEverywhere' => isset($config['show_bell_everywhere']) ? (bool)$config['show_bell_everywhere'] : true,
    'autoRefresh'        => isset($config['auto_refresh']) ? (bool)$config['auto_refresh'] : true
];

// Saída do JavaScript
echo "window.ticketAnswersConfig = " . json_encode($js_config) . ";";
echo "\nconsole.log('Ticket Answers: Configurações globais carregadas (Intervalo: ' + window.ticketAnswersConfig.checkInterval + 's)');";
