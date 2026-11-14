export default function initLearningAvatar() {
    document.querySelectorAll('[data-learning-avatar-form]').forEach(function (form) {
        const preview = document.getElementById(form.dataset.learningAvatarPreviewTarget);
        if (!preview) return;
        const balance = Number(form.dataset.learningAvatarBalance || 0);
        const summary = form.querySelector('[data-learning-avatar-cost-summary]');
        const submit = form.querySelector('[data-learning-avatar-submit]');
        const characterPreviewPayload = form.querySelector('[data-learning-avatar-character-previews]');
        let characterPreviewsByGrade = {};
        if (characterPreviewPayload) {
            try {
                characterPreviewsByGrade = JSON.parse(characterPreviewPayload.textContent || '{}') || {};
            } catch (error) {
                characterPreviewsByGrade = {};
            }
        }
        const escapeLearningAvatarSelectorValue = function (value) {
            return window.CSS && CSS.escape ? CSS.escape(value) : String(value).replace(/"/g, '\\"');
        };
        const insertLearningAvatarLayer = function (layer) {
            const order = Number(layer.dataset.learningAvatarLayerOrder || 99);
            const nextLayer = Array.from(preview.querySelectorAll('[data-learning-avatar-layer-order]')).find(function (existingLayer) {
                return Number(existingLayer.dataset.learningAvatarLayerOrder || 99) > order;
            });

            preview.insertBefore(layer, nextLayer || null);
        };

        const updateLearningAvatarSlot = function (select) {
            const selectedOption = select.options[select.selectedIndex];
            const equippedSlot = select.name.match(/^equipped\[([^\]]+)\]$/)?.[1];
            if (!equippedSlot) return;

            preview.querySelectorAll('[data-learning-avatar-layer-slot="' + escapeLearningAvatarSelectorValue(equippedSlot) + '"]').forEach(function (layer) {
                layer.remove();
            });

            if (!selectedOption || !selectedOption.value || !selectedOption.dataset.previewSrc) {
                return;
            }

            const slot = document.createElement('span');
            slot.className = 'gc-learning-avatar__slot gc-learning-avatar__slot--' + selectedOption.dataset.previewSlot;
            slot.dataset.learningAvatarLayerSlot = equippedSlot;
            slot.dataset.learningAvatarLayerOrder = selectedOption.dataset.previewOrder || '99';
            slot.setAttribute('style', selectedOption.dataset.previewStyle || '');

            const image = document.createElement('img');
            image.src = selectedOption.dataset.previewSrc;
            image.alt = '';
            image.loading = 'lazy';
            image.className = 'gc-learning-avatar__layer gc-learning-avatar__layer--item';
            image.style.objectFit = selectedOption.dataset.previewFit || 'contain';
            image.style.objectPosition = selectedOption.dataset.previewObjectPosition || 'center center';

            slot.appendChild(image);
            insertLearningAvatarLayer(slot);
        };

        const characterPreviewFromLayer = function (layer) {
            if (!layer || !layer.src) return null;

            return {
                src: preview.dataset.customCharacterUrl || layer.src,
                order: layer.order || '99',
                fullCanvas: layer.fullCanvas ? '1' : '0',
                slot: layer.slot || 'character',
                style: layer.style || '',
                innerStyle: layer.innerStyle || '',
                fit: layer.fit === 'cover' ? 'cover' : 'contain',
                objectPosition: layer.objectPosition || 'center bottom'
            };
        };

        const selectedCharacterPreview = function () {
            const genderSelect = form.querySelector('select[name="appearance[gender]"]');
            const gradeSelect = form.querySelector('select[name="appearance[grade]"]');
            const gender = genderSelect ? genderSelect.value : 'boy';
            const grade = gradeSelect ? gradeSelect.value : null;
            const gradePreview = grade ? characterPreviewsByGrade[grade]?.[gender] : null;

            if (gradePreview) {
                return characterPreviewFromLayer(gradePreview);
            }

            const selectedOption = genderSelect?.options[genderSelect.selectedIndex];
            if (!selectedOption || !selectedOption.dataset.characterSrc) return null;

            return {
                src: preview.dataset.customCharacterUrl || selectedOption.dataset.characterSrc,
                order: selectedOption.dataset.characterOrder || '99',
                fullCanvas: selectedOption.dataset.characterFullCanvas || '0',
                slot: selectedOption.dataset.characterSlot || 'character',
                style: selectedOption.dataset.characterStyle || '',
                innerStyle: selectedOption.dataset.characterInnerStyle || '',
                fit: selectedOption.dataset.characterFit || 'contain',
                objectPosition: selectedOption.dataset.characterObjectPosition || 'center bottom'
            };
        };

        const updateLearningAvatarCharacter = function (select) {
            const character = selectedCharacterPreview();
            if (!character) return;

            preview.querySelectorAll('[data-learning-avatar-layer-slot="character"], .gc-learning-avatar__layer--full').forEach(function (layer) {
                const src = layer.getAttribute('src') || '';
                const isCharacterLayer = layer.dataset.learningAvatarLayerSlot === 'character'
                    || src.indexOf('/characters/') !== -1
                    || src.indexOf('/20_character_skin_basic') !== -1;

                if (!isCharacterLayer) return;

                layer.remove();
            });

            const image = document.createElement('img');
            image.src = character.src;
            image.alt = '';
            image.loading = 'lazy';
            image.className = 'gc-learning-avatar__layer';
            image.dataset.learningAvatarLayerOrder = character.order || '99';

            if (character.fullCanvas === '0') {
                const slot = document.createElement('span');
                slot.className = 'gc-learning-avatar__slot gc-learning-avatar__slot--' + (character.slot || 'character');
                slot.dataset.learningAvatarLayerSlot = 'character';
                slot.dataset.learningAvatarLayerOrder = character.order || '99';
                slot.setAttribute('style', character.style || '');

                image.classList.add('gc-learning-avatar__layer--item');
                image.style.objectFit = character.fit || 'contain';
                image.style.objectPosition = character.objectPosition || 'center bottom';
                if (character.innerStyle) {
                    image.style.cssText += '; ' + character.innerStyle;
                }
                slot.appendChild(image);
                insertLearningAvatarLayer(slot);
                return;
            }

            image.classList.add('gc-learning-avatar__layer--full');
            image.dataset.learningAvatarLayerSlot = 'character';
            insertLearningAvatarLayer(image);
        };

        const updateLearningAvatarCost = function () {
            const purchased = new Map();

            form.querySelectorAll('select[name^="equipped["]').forEach(function (select) {
                const selectedOption = select.options[select.selectedIndex];
                if (!selectedOption || !selectedOption.value || selectedOption.dataset.itemOwned === '1') return;

                const cost = Number(selectedOption.dataset.itemCost || 0);
                if (cost > 0) {
                    purchased.set(selectedOption.value, {
                        name: selectedOption.textContent.replace(/\s+/g, ' ').trim().replace(/\s+·.+$/, ''),
                        cost: cost
                    });
                }
            });

            const total = Array.from(purchased.values()).reduce(function (sum, item) {
                return sum + item.cost;
            }, 0);

            if (!summary) return;

            summary.classList.remove('is-warning', 'is-muted');
            if (total <= 0) {
                summary.classList.add('is-muted');
                summary.innerHTML = '<span>Новых покупок нет</span>';
                form.removeAttribute('data-confirm');
                if (submit) submit.disabled = false;
                return;
            }

            const itemNames = Array.from(purchased.values()).map(function (item) {
                return item.name;
            }).join(', ');
            const canAfford = total <= balance;
            summary.classList.toggle('is-warning', !canAfford);
            summary.innerHTML = '<span>' + (canAfford ? 'Будет списано' : 'Не хватает GC') + '</span><strong>' + total + ' GC</strong>';
            form.dataset.confirm = 'Сохранить комнату и купить: ' + itemNames + ' за ' + total + ' GC?';
            if (submit) submit.disabled = !canAfford;
        };

        form.querySelectorAll('select[name^="equipped["]').forEach(function (select) {
            select.addEventListener('change', function () {
                updateLearningAvatarSlot(select);
                updateLearningAvatarCost();
            });
        });

        form.querySelectorAll('select[name="appearance[gender]"]').forEach(function (select) {
            select.addEventListener('change', function () {
                updateLearningAvatarCharacter(select);
            });
        });

        form.querySelectorAll('select[name="appearance[grade]"]').forEach(function (select) {
            select.addEventListener('change', function () {
                updateLearningAvatarCharacter(select);
            });
        });

        updateLearningAvatarCost();
    });
}
