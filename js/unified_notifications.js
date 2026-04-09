/**
 * Ticket Answers - Unified Notifications JavaScript
 * Handles the notification bell icon and AJAX polling
 */

// Garantir que CFG_GLPI está definido para evitar erros de referência
if (typeof CFG_GLPI === 'undefined') {
    window.CFG_GLPI = {
        root_doc: ''
    };
}

// Função para adicionar o sino de notificações à interface
function addNotificationBell() {
    console.log('Ticket Answers: Tentando adicionar o sino de notificações...');
    
    // Verificar se o sino já existe
    if ($('.notification-bell').length > 0) {
        console.log('Ticket Answers: Sino já existe');
        return;
    }
    
    let success = false;
    
    // 1. Menu de usuário no canto superior direito (Melhor opção para o layout moderno do GLPI)
    if (!success) {
        const userMenu = $('.navbar .navbar-nav:last-child, .navbar .ms-auto, header .navbar-nav:last-child, .user-menu');
        if (userMenu.length > 0) {
            console.log('Ticket Answers: Localizado menu de usuário');
            const bellItem = $('<li class="nav-item" style="display: flex; align-items: center; margin-right: 15px;"></li>');
            bellItem.append(getNotificationButton());
            userMenu.prepend(bellItem);
            setupBellEvents(bellItem);
            success = true;
        }
    }
    
    // 2. Método legado: campo de pesquisa global
    if (!success) {
        const global_search = $('input[name="globalsearch"], input.form-control-search, .search-input');
        if (global_search.length > 0) {
            let container = global_search.closest('.input-group');
            if (container.length === 0) {
                container = global_search.parent();
            }
            if (container.length > 0) {
                const wrapper = $('<div class="notification-search-wrapper" style="display: flex; align-items: center; margin-left: 10px;"></div>');
                wrapper.append(getNotificationButton());
                container.after(wrapper);
                setupBellEvents(wrapper);
                success = true;
                console.log('Ticket Answers: Adicionado após campo de busca');
            }
        }
    }
    
    // 3. FormCreator
    if (!success) {
        const formcreatorHeader = $('.plugin_formcreator_userForm_header, .plugin_formcreator_header');
        if (formcreatorHeader.length > 0) {
            const bellContainer = $('<div class="notification-container" style="margin-left: auto; margin-right: 15px; display: flex; align-items: center;"></div>');
            bellContainer.append(getNotificationButton());
            formcreatorHeader.append(bellContainer);
            setupBellEvents(bellContainer);
            success = true;
        }
    }
    
    // 4. Cabeçalho da interface simplificada (Self-Service)
    if (!success) {
        const selfServiceHeader = $('.navbar.self-service, .self-service .navbar, .self-service-header, .navbar-nav.login-info');
        if (selfServiceHeader.length > 0) {
            const bellContainer = $('<div class="notification-nav-item" style="display: flex; align-items: center; margin-right: 15px;"></div>');
            bellContainer.append(getNotificationButton());
            selfServiceHeader.prepend(bellContainer);
            setupBellEvents(bellContainer);
            success = true;
        }
    }
    
    // 5. Último recurso: flutuante
    if (!success) {
        console.log('Ticket Answers: Usando fallback flutuante');
        const floatingBell = $(`
            <div class="floating-notification-container" style="
                position: fixed; top: 10px; right: 10px; z-index: 9999;
                display: flex; background-color: #f8f9fa; padding: 5px;
                border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            "></div>
        `);
        floatingBell.append(getNotificationButton());
        $('body').append(floatingBell);
        setupBellEvents(floatingBell);
        success = true;
    }
}

// Função para obter o botão de notificação
function getNotificationButton() {
    const soundEnabled = getSoundEnabledState();
    const soundClass = soundEnabled ? 'sound-enabled' : 'sound-disabled';
    const soundTitle = soundEnabled ? 'Notificações (som ativado)' : 'Notificações (som desativado)';
    
    return $(`
        <button type="button" class="notification-bell btn btn-outline-secondary ${soundClass}" title="${soundTitle}">
            <i class="fas fa-bell fa-lg"></i>
        </button>`);
}

// Configurar eventos de clique
function setupBellEvents(container) {
    container.find('.notification-bell').on('click', function() {
        window.location.href = CFG_GLPI.root_doc + '/plugins/ticketanswers/front/index.php';
    });
    
    // Clique com o botão direito alterna o som
    container.find('.notification-bell').on('contextmenu', function(e) {
        e.preventDefault();
        toggleNotificationSound();
        return false;
    });
}

// Alternar som
function toggleNotificationSound() {
    let soundEnabled = !getSoundEnabledState();
    const button = $('.notification-bell');
    
    if (soundEnabled) {
        button.addClass('sound-enabled').removeClass('sound-disabled');
        button.attr('title', 'Notificações (som ativado)');
        playTestSound();
    } else {
        button.addClass('sound-disabled').removeClass('sound-enabled');
        button.attr('title', 'Notificações (som desativado)');
    }
    
    try {
        localStorage.setItem('ticketAnswersSoundEnabled', soundEnabled ? 'true' : 'false');
    } catch (e) {}
}

// Obter estado do som
function getSoundEnabledState() {
    try {
        const saved = localStorage.getItem('ticketAnswersSoundEnabled');
        if (saved !== null) return saved === 'true';
    } catch (e) {}
    
    return window.ticketAnswersConfig && typeof window.ticketAnswersConfig.enableSound !== 'undefined'
        ? window.ticketAnswersConfig.enableSound
        : true;
}

// Som de teste
function playTestSound() {
    try {
        const audio = new Audio(CFG_GLPI.root_doc + '/plugins/ticketanswers/sound/notification.mp3');
        audio.volume = 0.2;
        audio.play().catch(() => {});
    } catch (e) {}
}

// Som de notificação real
function playNotificationSound() {
    if (!getSoundEnabledState()) return;
    
    try {
        const now = Date.now();
        if ((now - (window.lastSoundPlayed || 0)) < 5000) return;
        window.lastSoundPlayed = now;
        
        let audio = document.getElementById('notification-sound');
        if (!audio) {
            audio = document.createElement('audio');
            audio.id = 'notification-sound';
            audio.src = CFG_GLPI.root_doc + '/plugins/ticketanswers/sound/notification.mp3';
            document.body.appendChild(audio);
        }
        
        audio.volume = (window.ticketAnswersConfig && window.ticketAnswersConfig.soundVolume)
            ? window.ticketAnswersConfig.soundVolume / 100
            : 0.5;
        audio.currentTime = 0;
        audio.play().catch(e => console.log('Audio play blocked'));
    } catch (e) {}
}

// Estilos CSS
function addNotificationStyles() {
    const css = `
        @keyframes bell-shake {
            0% { transform: rotate(0); }
            5% { transform: rotate(15deg); }
            10% { transform: rotate(-15deg); }
            15% { transform: rotate(10deg); }
            20% { transform: rotate(-10deg); }
            25% { transform: rotate(5deg); }
            30% { transform: rotate(-5deg); }
            35% { transform: rotate(0); }
            100% { transform: rotate(0); }
        }
        .bell-shake-animation i {
            animation: bell-shake 2s ease-in-out;
            transform-origin: top center;
        }
        .notification-bell { position: relative; }
        .notification-bell .has-notifications { color: #ff0000 !important; }
        .notification-count {
            position: absolute; top: -8px; right: -8px;
            background: linear-gradient(135deg, #ff4b2b, #ff416c);
            color: white; border-radius: 10px; padding: 1px 5px;
            font-size: 11px; font-weight: 800; line-height: 1;
            min-width: 16px; height: 16px; display: flex;
            align-items: center; justify-content: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.3); border: 1.5px solid #fff;
            z-index: 10; pointer-events: none;
        }
        .notification-bell.sound-disabled:after {
            content: ''; position: absolute; bottom: 3px; right: 3px;
            width: 8px; height: 8px; background-color: #ccc; border-radius: 50%;
        }
        .notification-bell.sound-enabled:after {
            content: ''; position: absolute; bottom: 3px; right: 3px;
            width: 8px; height: 8px; background-color: #4CAF50; border-radius: 50%;
        }
    `;
    $('<style>').prop('type', 'text/css').html(css).appendTo('head');
}

// Decidir se mostra o sino
function shouldShowBell() {
    if (window.ticketAnswersConfig && typeof window.ticketAnswersConfig.showBellEverywhere !== 'undefined') {
        return window.ticketAnswersConfig.showBellEverywhere;
    }
    return true;
}

// Atualizar contador
function updateNotificationCount(count) {
    const bellBtn = $('.notification-bell');
    const bellIcon = bellBtn.find('i');
    
    if (count > 0) {
        bellIcon.addClass('has-notifications');
        let badge = bellBtn.find('.notification-count');
        if (badge.length === 0) {
            bellBtn.append('<span class="notification-count">' + count + '</span>');
        } else {
            badge.text(count);
        }
    } else {
        bellIcon.removeClass('has-notifications');
        bellBtn.find('.notification-count').remove();
    }
}

// Pooling de notificações
function checkNotifications() {
    $.ajax({
        url: CFG_GLPI.root_doc + '/plugins/ticketanswers/ajax/check_all_notifications.php',
        type: 'GET',
        dataType: 'json',
        success: (data) => {
            const currentCount = data.combined_count || data.count || 0;
            const previousCount = window.lastNotificationCount || 0;
            
            updateNotificationCount(currentCount);
            
            if (currentCount > previousCount) {
                $('.notification-bell').addClass('bell-shake-animation');
                setTimeout(() => $('.notification-bell').removeClass('bell-shake-animation'), 3000);
                playNotificationSound();
                
                if (Notification.permission === "granted") {
                    new Notification("GLPI", { 
                        body: "Você tem " + currentCount + " novas notificações",
                        icon: CFG_GLPI.root_doc + "/pics/favicon.ico"
                    }).onclick = () => {
                        window.focus();
                        window.location.href = CFG_GLPI.root_doc + '/plugins/ticketanswers/front/index.php';
                    };
                }
            }
            window.lastNotificationCount = currentCount;
        },
        error: (xhr) => console.error('Ticket Answers: Erro polling', xhr.status)
    });
}

// Inicialização
function initNotificationBell() {
    addNotificationStyles();
    if (shouldShowBell()) {
        addNotificationBell();
        
        // Solicitar permissão de notificação nativa
        if ("Notification" in window && Notification.permission !== "granted" && Notification.permission !== "denied") {
            Notification.requestPermission();
        }

        setTimeout(() => {
            checkNotifications();
            const interval = (window.ticketAnswersConfig && window.ticketAnswersConfig.checkInterval) 
                ? window.ticketAnswersConfig.checkInterval * 1000 
                : 300000;
            window.notificationInterval = setInterval(checkNotifications, interval);
        }, 1000);
        
        // Desbloquear áudio na primeira interação
        $(document).one('click', () => {
            try { new Audio().play().catch(() => {}); } catch(e) {}
        });
    }
}

// Document Ready
$(document).ready(() => {
    if (!window.TicketAnswersInitialized) {
        window.TicketAnswersInitialized = true;
        setTimeout(initNotificationBell, 500);
    }
});

// Exportar API global
window.NotificationBell = {
    updateBellCount: function() {
        $.ajax({
            url: CFG_GLPI.root_doc + '/plugins/ticketanswers/ajax/check_all_notifications.php',
            type: 'GET',
            dataType: 'json',
            success: (data) => {
                const count = data.combined_count || data.count || 0;
                updateNotificationCount(count);
                window.lastNotificationCount = count;
            }
        });
    }
};

// Interceptor AJAX para atualizar contador quando deletar/ler
$(document).ready(function() {
    var originalAjax = $.ajax;
    $.ajax = function(options) {
        if (options.url && (
            options.url.indexOf('mark_as_read.php') !== -1 ||
            options.url.indexOf('mark_all_as_read.php') !== -1 ||
            options.url.indexOf('mark_notification_as_read.php') !== -1
        )) {
            var originalSuccess = options.success;
            options.success = function(response) {
                if (originalSuccess) originalSuccess(response);
                setTimeout(() => {
                    if (window.NotificationBell) window.NotificationBell.updateBellCount();
                }, 500);
            };
        }
        return originalAjax.apply(this, arguments);
    };
});
