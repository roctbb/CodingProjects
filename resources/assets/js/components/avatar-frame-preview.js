import { sanitizeColor, frameCostFor, gradientFor, shapeFor, patternFor, frameAnimationFor, avatarEffectFor } from "./avatar-frame-config.js";

export function createAvatarFramePreview(editor) {
    var previews = Array.from(editor.querySelectorAll('[data-avatar-frame-preview]'));
    var animatedOptionsPanel = editor.querySelector('.profile-avatar-frame-editor__animation-options');
    var animatedOptions = Array.from(editor.querySelectorAll('[data-frame-animated-option]'));
    var patternStrengthField = editor.querySelector('[data-frame-pattern-strength-field]');
    var avatarEffectStrengthField = editor.querySelector('[data-frame-avatar-effect-strength-field]');
    var angleField = editor.querySelector('[data-frame-angle-field]');
    var angleValue = editor.querySelector('[data-frame-angle-value]');
    var widthValue = editor.querySelector('[data-frame-width-value]');
    var glowValue = editor.querySelector('[data-frame-glow-value]');
    var speedValue = editor.querySelector('[data-frame-speed-value]');
    var patternOpacityValue = editor.querySelector('[data-frame-pattern-opacity-value]');
    var effectOpacityValue = editor.querySelector('[data-frame-effect-opacity-value]');
    var summaryType = editor.querySelector('[data-frame-summary-type]');
    var summaryShape = editor.querySelector('[data-frame-summary-shape]');
    var summaryPattern = editor.querySelector('[data-frame-summary-pattern]');
    var summaryAnimation = editor.querySelector('[data-frame-summary-animation]');
    var priceValue = editor.querySelector('[data-frame-price]');
    var userBalance = parseInt(editor.dataset.frameBalance || '', 10);
    var colorInputs = Array.from(editor.querySelectorAll('input[type="color"]'));
    var submitButton = editor.querySelector('[data-frame-submit]');
    var setFrameInputDisabled = function (input, disabled) {
        input.disabled = input.dataset.frameInitiallyDisabled === '1' || disabled;
    };

    var labelFor = function (items, value, fallback) {
        return items[value] || fallback;
    };

    return function applyConfig(config) {
        var glowColor = config.colors[1] || config.colors[0] || '#22d3ee';
        var shape = shapeFor(config.shape);
        var avatarEffect = avatarEffectFor(config);

        previews.forEach(function (preview) {
            preview.style.setProperty('--profile-avatar-frame-bg', gradientFor(config));
            preview.style.setProperty('--profile-avatar-frame-width', config.width + 'px');
            preview.style.setProperty('--profile-avatar-frame-radius', shape[0]);
            preview.style.setProperty('--profile-avatar-frame-inner-radius', shape[1]);
            preview.style.setProperty('--profile-avatar-frame-pattern', patternFor(config.pattern));
            preview.style.setProperty('--profile-avatar-pattern-opacity', config.patternOpacity + '%');
            preview.style.setProperty('--profile-avatar-frame-shadow', '0 0 0 0.12rem color-mix(in srgb, ' + config.colors[0] + ' 18%, transparent), 0 0.8rem 2rem color-mix(in srgb, ' + glowColor + ' ' + config.glow + '%, transparent)');
            preview.style.setProperty('--profile-avatar-frame-animation', frameAnimationFor(config));
            preview.style.setProperty('--profile-avatar-effect-bg', avatarEffect[0]);
            preview.style.setProperty('--profile-avatar-effect-animation', avatarEffect[1]);
            preview.style.setProperty('--profile-avatar-effect-opacity', avatarEffect[2]);
        });

        colorInputs.forEach(function (input) {
            var label = input.closest('.profile-avatar-frame-color');
            if (label) label.style.setProperty('--frame-color', sanitizeColor(input.value));
        });

        if (angleValue) angleValue.textContent = config.angle + '°';
        if (widthValue) widthValue.textContent = config.width + 'px';
        if (glowValue) glowValue.textContent = config.glow + '%';
        if (speedValue) speedValue.textContent = config.speed + '%';
        if (patternOpacityValue) patternOpacityValue.textContent = config.patternOpacity + '%';
        if (effectOpacityValue) effectOpacityValue.textContent = config.effectOpacity + '%';
        if (angleField) angleField.classList.toggle('d-none', config.type === 'radial');
        if (patternStrengthField) {
            var patternHidden = config.pattern === 'none';
            patternStrengthField.classList.toggle('d-none', patternHidden);
            patternStrengthField.querySelectorAll('input').forEach(function (input) {
                setFrameInputDisabled(input, patternHidden);
            });
        }
        if (summaryType) {
            summaryType.textContent = labelFor({ linear: 'Линия', conic: 'Круг', radial: 'Сияние' }, config.type, 'Градиент');
        }
        if (summaryShape) {
            summaryShape.textContent = labelFor({ circle: 'Круглая', squircle: 'Сквиркл', badge: 'Жетон', soft: 'Мягкая' }, config.shape, 'Форма');
        }
        if (summaryPattern) {
            summaryPattern.textContent = config.pattern === 'none'
                ? 'Без паттерна'
                : labelFor({ sparkles: 'Искры', pixels: 'Пиксели', stripes: 'Штрихи' }, config.pattern, 'Паттерн') + ' · ' + config.patternOpacity + '%';
        }
        if (summaryAnimation) {
            summaryAnimation.textContent = config.animated
                ? labelFor({ spin: 'Вращение', pulse: 'Пульс', sweep: 'Блик', still: 'Статика' }, config.motion, 'Живая') + ' · ' + config.speed + '%'
                : 'Статичная';
        }
        if (submitButton) {
            var frameCost = frameCostFor(config, submitButton.dataset);
            var frameType = config.animated ? 'живую' : 'статичную';
            var confirmTemplate = submitButton.dataset.frameConfirmTemplate || 'Купить свою {type} рамку за {cost} GC?';

            if (priceValue) priceValue.textContent = frameCost;
            const expectedCost = editor.querySelector('[data-frame-expected-cost]');
            if (expectedCost) expectedCost.value = frameCost;
            submitButton.dataset.confirm = confirmTemplate
                .replace('{type}', frameType)
                .replace('{cost}', frameCost);
            submitButton.disabled = submitButton.dataset.frameInitiallyDisabled === '1'
                || (!Number.isNaN(userBalance) && frameCost > userBalance);
            const fundsNote = editor.querySelector('[data-frame-funds-note]');
            if (fundsNote) {
                const missing = Number.isNaN(userBalance) ? 0 : Math.max(0, frameCost - userBalance);
                fundsNote.hidden = missing === 0;
                fundsNote.textContent = missing ? `Для выбранной рамки нужно ещё ${missing} GC.` : '';
            }
        }

        animatedOptions.forEach(function (option) {
            var optionHidden = !config.animated || (option === avatarEffectStrengthField && config.avatarEffect === 'none');
            option.classList.toggle('d-none', optionHidden);
            option.querySelectorAll('input').forEach(function (input) {
                setFrameInputDisabled(input, optionHidden);
            });
        });
        if (animatedOptionsPanel) {
            animatedOptionsPanel.classList.toggle('d-none', !config.animated);
        }
    };

}
