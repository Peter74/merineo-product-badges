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
                wrap.className = 'merineo-pb-badge-item';

                var labelInput = document.createElement('input');
                labelInput.type = 'text';
                labelInput.className = 'regular-text';
                labelInput.value = badge.label || '';
                labelInput.placeholder = (window.merineoProductBadgesL10n && merineoProductBadgesL10n.labelPlaceholder) || 'Text for badge';
                labelInput.addEventListener('input', function(){
                    badge.label = labelInput.value;
                    update();
                });

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

                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'button-link-delete';
                removeBtn.textContent = '×';
                removeBtn.addEventListener('click', function(){
                    badges.splice(index,1);
                    update();
                });

                wrap.appendChild(labelInput);
                wrap.appendChild(bgInput);
                wrap.appendChild(textInput);
                wrap.appendChild(removeBtn);
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
        var boxes = document.querySelectorAll('.merineo-pb-term-badges');
        boxes.forEach(function(box){initBox(box);});
    });
})();
