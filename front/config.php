<?php
include ("../../../inc/includes.php");

// Proteção: apenas administradores com direito "config" podem acessar
Session::checkRight("config", READ);

Html::header("Ticket Answers", $_SERVER['PHP_SELF'], "plugins", "PluginTicketanswersMenu", "config");

// Salvar configurações se o formulário foi enviado
if (isset($_POST['update'])) {
    Session::checkRight("config", UPDATE);
    
    // Salvar configurações
    Config::setConfigurationValues('plugin:ticketanswers', [
        'check_interval'         => intval($_POST['check_interval']),
        'enable_sound'           => isset($_POST['enable_sound']) ? 1 : 0,
        'sound_volume'           => intval($_POST['sound_volume']),
        'notifications_per_page' => intval($_POST['notifications_per_page']),
        'auto_refresh'           => isset($_POST['auto_refresh']) ? 1 : 0,
        'show_bell_everywhere'    => isset($_POST['show_bell_everywhere']) ? 1 : 0
    ]);
    
    Session::addMessageAfterRedirect(__('Configurações salvas com sucesso', 'ticketanswers'), true, INFO);
    Html::back();
}

// Obter configurações atuais
$config = Config::getConfigurationValues('plugin:ticketanswers');

// Valores padrão
$check_interval = $config['check_interval'] ?? 300; // 5 minutos padrão
$enable_sound = $config['enable_sound'] ?? 1;
$sound_volume = $config['sound_volume'] ?? 70;
$notifications_per_page = $config['notifications_per_page'] ?? 30;
$auto_refresh = $config['auto_refresh'] ?? 1;
$show_bell_everywhere = $config['show_bell_everywhere'] ?? 1;

echo "<div class='center'>";
echo "<h1>" . __("Configuração do Ticket Answers", "ticketanswers") . "</h1>";

echo "<form name='form' method='post' action='" . $_SERVER['PHP_SELF'] . "'>";
echo "<table class='tab_cadre_fixe'>";
echo "<tr><th colspan='2'>" . __("Configurações Gerais das Notificações", "ticketanswers") . "</th></tr>";

// Intervalo de verificação
echo "<tr class='tab_bg_1'>";
echo "<td>" . __("Intervalo de verificação global (segundos)", "ticketanswers") . " <span style='color: red;'>*</span></td>";
echo "<td>";
echo "<input type='number' name='check_interval' value='$check_interval' min='10' max='3600' class='form-control' style='width: 100px;'>";
echo " <small>" . __("Frequência com que o sino buscará novas notificações.", "ticketanswers") . "</small>";
echo "</td>";
echo "</tr>";

// Mostrar sino em todo lugar
echo "<tr class='tab_bg_1'>";
echo "<td>" . __("Exibir sino em todas as páginas", "ticketanswers") . "</td>";
echo "<td>";
echo "<input type='checkbox' name='show_bell_everywhere' " . ($show_bell_everywhere ? "checked" : "") . ">";
echo "</td>";
echo "</tr>";

// Habilitar som
echo "<tr class='tab_bg_1'>";
echo "<td>" . __("Habilitar notificação sonora", "ticketanswers") . "</td>";
echo "<td>";
echo "<input type='checkbox' name='enable_sound' " . ($enable_sound ? "checked" : "") . ">";
echo "</td>";
echo "</tr>";

// Volume do som
echo "<tr class='tab_bg_1'>";
echo "<td>" . __("Volume do som (0-100)", "ticketanswers") . "</td>";
echo "<td>";
echo "<input type='range' name='sound_volume' min='0' max='100' value='$sound_volume' style='width: 200px;'>";
echo " <span id='volume_val'>$sound_volume</span>%";
echo "</td>";
echo "</tr>";

// Notificações por página
echo "<tr class='tab_bg_1'>";
echo "<td>" . __("Notificações por página na listagem", "ticketanswers") . "</td>";
echo "<td>";
echo "<input type='number' name='notifications_per_page' value='$notifications_per_page' min='5' max='100' class='form-control' style='width: 100px;'>";
echo "</td>";
echo "</tr>";

// Atualização automática da página de lista
echo "<tr class='tab_bg_1'>";
echo "<td>" . __("Atualizar automaticamente a página de notificações aberta", "ticketanswers") . "</td>";
echo "<td>";
echo "<input type='checkbox' name='auto_refresh' " . ($auto_refresh ? "checked" : "") . ">";
echo "</td>";
echo "</tr>";

echo "<tr class='tab_bg_2'>";
echo "<td colspan='2' class='center'>";
echo "<input type='submit' name='update' class='submit' value=\"" . _sx('button', 'Salvar') . "\">";
echo "</td>";
echo "</tr>";

echo "</table>";
Html::closeForm();
echo "</div>";

// Script para o range de volume
echo "<script>
$(document).ready(function() {
    $('input[name=\"sound_volume\"]').on('input', function() {
        $('#volume_val').text($(this).val());
    });
    
    // Botão de teste
    $('#test-sound-button').on('click', function() {
        $('#sound-test-result').html('<div class=\"alert alert-info\">Tentando reproduzir som...</div>');
        try {
            var audio = new Audio(CFG_GLPI.root_doc + '/plugins/ticketanswers/sound/notification.mp3');
            audio.volume = $('input[name=\"sound_volume\"]').val() / 100;
            audio.play().then(() => {
                $('#sound-test-result').html('<div class=\"alert alert-success\">Som reproduzido com sucesso!</div>');
            }).catch(e => {
                $('#sound-test-result').html('<div class=\"alert alert-danger\">Erro: ' + e.message + '</div>');
            });
        } catch (e) {
            $('#sound-test-result').html('<div class=\"alert alert-danger\">Exceção: ' + e.message + '</div>');
        }
    });
});
</script>";

echo "<div class='center' style='margin-top: 20px;'>";
echo "<button type='button' class='btn btn-primary' id='test-sound-button'><i class='fas fa-play'></i> " . __("Testar Som", "ticketanswers") . "</button>";
echo "<div id='sound-test-result' style='margin-top: 10px; max-width: 400px; margin-left: auto; margin-right: auto;'></div>";
echo "</div>";

Html::footer();