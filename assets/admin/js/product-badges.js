(function(){
    'use strict';

    function initBox(boxEl){
        var inputSelector = boxEl.getAttribute('data-merineo-pb-target');
        var input = document.querySelector(inputSelector);
        if(!input){return;}

        var badges = [];
        try{
            var parsed = JSON.parse(input.value || '[]');
            if(Array.isArray(parsed)){badges = parsed;}
        }catch(e){}

        var listEl = boxEl.querySelector('.merineo-pb-badges-list');
        var addBtn = boxEl.querySelector('.merineo-pb-add-badge');

        function render(){
            listEl.innerHTML = '';
            if(!badges.length){
                var p = document.createElement('p');
                p.textContent = (window.merineoProductBadgesL10n && merineoProductBadgesL10n.noBadges) || 'No badges yet.';
                listEl.appendChild(p);
            }
            badges.forEach(function(badge, index){
                var wrap = document.createElement('div');
                wrap.className = 'merineo-pb-badge-item postbox';
                var inner = document.createElement('div');
                inner.className = 'inside';

                var header = document.createElement('div');
                header.className = 'merineo-pb-badge-header';

                var title = document.createElement('strong');
                title.textContent = badge.label || ((window.merineoProductBadgesL10n && merineoProductBadgesL10n.badge) || 'Badge') + ' ' + (index+1);

                var remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'button-link-delete';
                remove.textContent = '×';
                remove.addEventListener('click', function(){
                    badges.splice(index,1);
                    update();
                });

                header.appendChild(title);
                header.appendChild(remove);

                var labelP = document.createElement('p');
                var labelInput = document.createElement('input');
                labelInput.type = 'text';
                labelInput.className = 'widefat';
                labelInput.value = badge.label || '';
                labelInput.placeholder = (window.merineoProductBadgesL10n && merineoProductBadgesL10n.labelPlaceholder) || 'Text for badge';
                labelInput.addEventListener('input', function(){
                    badge.label = labelInput.value;
                    title.textContent = badge.label || ((window.merineoProductBadgesL10n && merineoProductBadgesL10n.badge) || 'Badge') + ' ' + (index+1);
                    update();
                });
                labelP.appendChild(labelInput);

                var colorsRow = document.createElement('div');
                colorsRow.className = 'merineo-pb-badge-colors';

                var bgInput = document.createElement('input');
                bgInput.type = 'text';
                bgInput.className = 'merineo-pb-color-field';
                bgInput.value = badge.bg_color || '';
                bgInput.placeholder = '#000000';
                bgInput.addEventListener('change', function(){
                    badge.bg_color = bgInput.value;
                    update();
                });

                var textInput = document.createElement('input');
                textInput.type = 'text';
                textInput.className = 'merineo-pb-color-field';
                textInput.value = badge.text_color || '';
                textInput.placeholder = '#ffffff';
                textInput.addEventListener('change', function(){
                    badge.text_color = textInput.value;
                    update();
                });

                colorsRow.appendChild(bgInput);
                colorsRow.appendChild(textInput);

                inner.appendChild(header);
                inner.appendChild(labelP);
                inner.appendChild(colorsRow);
                wrap.appendChild(inner);
                listEl.appendChild(wrap);
            });

            if(window.jQuery && jQuery.fn.wpColorPicker){
                jQuery(boxEl).find('.merineo-pb-color-field').wpColorPicker();
            }
        }

        function update(){
            input.value = JSON.stringify(badges);
            render();
        }

        if(addBtn){
            addBtn.addEventListener('click', function(){
                badges.push({label:'',bg_color:'',text_color:''});
                update();
            });
        }

        render();
    }

    document.addEventListener('DOMContentLoaded', function(){
        var boxes = document.querySelectorAll('.merineo-pb-badges-box');
        boxes.forEach(function(box){initBox(box);});
    });
})();
