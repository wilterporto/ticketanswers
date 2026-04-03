$(document).ready(function() {
    // Função minimalista para garantir o alinhamento dos ícones sem destruir o DOM
    function fixButtonLayout() {
        const bell = $('.notification-bell');
        const sound = $('.sound-toggle');
        
        if (bell.length > 0) {
            // Estilos básicos individuais
            bell.css({
                'position': 'relative',
                'display': 'inline-block',
                'vertical-align': 'middle'
            });

            const container = bell.parent();
            
            // NÃO aplicar flex ao container se for o grupo de pesquisa padrão do GLPI
            // pois isso quebra o estilo 'input-group' do Bootstrap
            if (container.hasClass('input-group') || container.closest('.input-group').length > 0) {
                console.log('Fix Layout: Ignorando container de pesquisa para preservar estilo original');
                return;
            }

            // Aplicar alinhamento apenas se houver o botão de som ao lado
            if (sound.length > 0) {
                container.css({
                    'display': 'inline-flex',
                    'flex-direction': 'row',
                    'align-items': 'center',
                    'gap': '5px',
                    'margin-left': '8px'
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