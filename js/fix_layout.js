$(document).ready(function() {
    // Função minimalista para garantir o alinhamento dos ícones sem destruir o DOM
    function fixButtonLayout() {
        const bell = $('.notification-bell');
        const sound = $('.sound-toggle');
        
        if (bell.length > 0) {
            // Garantir que o sino tenha posição relativa para o contador
            bell.css({
                'position': 'relative',
                'display': 'inline-block',
                'vertical-align': 'middle'
            });

            // Se houver um botão de som separado, alinhar horizontalmente
            if (sound.length > 0) {
                const container = bell.parent();
                container.css({
                    'display': 'inline-flex',
                    'flex-direction': 'row',
                    'align-items': 'center',
                    'gap': '5px'
                });
            }
        }
    }
    
    // Executar a correção com atrasos seguros
    setTimeout(fixButtonLayout, 500);
    setTimeout(fixButtonLayout, 1500);
    
    $(window).on('resize', function() {
        fixButtonLayout();
    });
});