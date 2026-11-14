import initAvatarFrameDialogs from "./avatar-frame-dialog";
import { defaultFrameConfig, randomPalettes, sanitizeColor, normalizeFrameConfig } from "./avatar-frame-config";
import { createAvatarFramePreview } from "./avatar-frame-preview";

export default function initAvatarFrameEditor() {
    initAvatarFrameDialogs();
    document.querySelectorAll('[data-avatar-frame-editor]').forEach(function (editor) {
        if (!editor.querySelector('[data-avatar-frame-preview]')) return;

        var previewStage = editor.querySelector('[data-frame-preview-stage]');
        var typeInputs = Array.from(editor.querySelectorAll('[name="avatar_frame_config[type]"]'));
        var shapeInputs = Array.from(editor.querySelectorAll('[name="avatar_frame_config[shape]"]'));
        var motionInputs = Array.from(editor.querySelectorAll('[name="avatar_frame_config[motion]"]'));
        var patternInputs = Array.from(editor.querySelectorAll('[name="avatar_frame_config[pattern]"]'));
        var avatarEffectInputs = Array.from(editor.querySelectorAll('[name="avatar_frame_config[avatar_effect]"]'));
        var colorInputs = Array.from(editor.querySelectorAll('input[type="color"]'));
        var angleInput = editor.querySelector('[data-frame-angle]');
        var widthInput = editor.querySelector('[data-frame-width]');
        var glowInput = editor.querySelector('[data-frame-glow]');
        var speedInput = editor.querySelector('[data-frame-speed]');
        var patternOpacityInput = editor.querySelector('[data-frame-pattern-opacity]');
        var effectOpacityInput = editor.querySelector('[data-frame-effect-opacity]');
        var animatedInput = editor.querySelector('[name="avatar_frame_config[animated]"][type="checkbox"]');
        var submitButton = editor.querySelector('[data-frame-submit]');
        var previewBgClasses = ['is-preview-light', 'is-preview-dark'];
        editor.querySelectorAll('input, select').forEach(function (input) {
            input.dataset.frameInitiallyDisabled = input.disabled ? '1' : '0';
        });
        if (submitButton) {
            submitButton.dataset.frameInitiallyDisabled = submitButton.disabled ? '1' : '0';
        }

        var selectedValue = function (inputs, fallback) {
            var select = inputs.find(function (input) {
                return input.tagName === 'SELECT';
            });
            if (select) return select.value || fallback;

            var checked = inputs.find(function (input) {
                return input.checked;
            });

            return checked ? checked.value : fallback;
        };

        var currentConfig = function () {
            return normalizeFrameConfig({
                type: selectedValue(typeInputs, 'linear'),
                shape: selectedValue(shapeInputs, 'circle'),
                motion: selectedValue(motionInputs, 'spin'),
                pattern: selectedValue(patternInputs, 'none'),
                avatarEffect: selectedValue(avatarEffectInputs, 'sheen'),
                colors: colorInputs.map(input => input.value),
                angle: angleInput ? angleInput.value : 135,
                width: widthInput ? widthInput.value : 6,
                glow: glowInput ? glowInput.value : 28,
                speed: speedInput ? speedInput.value : 100,
                patternOpacity: patternOpacityInput ? patternOpacityInput.value : 72,
                effectOpacity: effectOpacityInput ? effectOpacityInput.value : 70,
                animated: animatedInput ? animatedInput.checked : true,
            });
        };

        var setPresetActive = function (activeButton) {
            editor.querySelectorAll('[data-frame-preset]').forEach(function (item) {
                item.classList.toggle('is-active', item === activeButton);
            });
        };

        var applyPresetConfig = function (config, activeButton) {
            config = config || {};

            typeInputs.forEach(function (input) {
                if (input.tagName === 'SELECT') {
                    input.value = config.type || defaultFrameConfig.type;
                    return;
                }
                input.checked = input.value === (config.type || defaultFrameConfig.type);
            });
            shapeInputs.forEach(function (input) {
                if (input.tagName === 'SELECT') {
                    input.value = config.shape || defaultFrameConfig.shape;
                    return;
                }
                input.checked = input.value === (config.shape || defaultFrameConfig.shape);
            });
            motionInputs.forEach(function (input) {
                if (input.tagName === 'SELECT') {
                    input.value = config.motion || defaultFrameConfig.motion;
                    return;
                }
                input.checked = input.value === (config.motion || defaultFrameConfig.motion);
            });
            patternInputs.forEach(function (input) {
                if (input.tagName === 'SELECT') {
                    input.value = config.pattern || defaultFrameConfig.pattern;
                    return;
                }
                input.checked = input.value === (config.pattern || defaultFrameConfig.pattern);
            });
            avatarEffectInputs.forEach(function (input) {
                if (input.tagName === 'SELECT') {
                    input.value = config.avatar_effect || defaultFrameConfig.avatar_effect;
                    return;
                }
                input.checked = input.value === (config.avatar_effect || defaultFrameConfig.avatar_effect);
            });

            colorInputs.forEach(function (input, index) {
                input.value = sanitizeColor((config.colors && config.colors[index]) || defaultFrameConfig.colors[index] || defaultFrameConfig.colors[0]);
            });

            if (angleInput) angleInput.value = config.angle !== undefined ? config.angle : defaultFrameConfig.angle;
            if (widthInput) widthInput.value = config.width !== undefined ? config.width : defaultFrameConfig.width;
            if (glowInput) glowInput.value = config.glow !== undefined ? config.glow : defaultFrameConfig.glow;
            if (speedInput) speedInput.value = config.speed !== undefined ? config.speed : defaultFrameConfig.speed;
            if (patternOpacityInput) patternOpacityInput.value = config.pattern_opacity !== undefined ? config.pattern_opacity : defaultFrameConfig.pattern_opacity;
            if (effectOpacityInput) effectOpacityInput.value = config.effect_opacity !== undefined ? config.effect_opacity : defaultFrameConfig.effect_opacity;
            if (animatedInput) animatedInput.checked = config.animated !== undefined ? Boolean(config.animated) : defaultFrameConfig.animated;

            setPresetActive(activeButton || null);
            applyConfig();
        };

        var randomItem = function (items) {
            return items[Math.floor(Math.random() * items.length)];
        };

        var setPaletteActive = function (activeButton) {
            editor.querySelectorAll('[data-frame-palette]').forEach(function (item) {
                item.classList.toggle('is-active', item === activeButton);
            });
        };

        var applyPalette = function (colors, activeButton) {
            if (!Array.isArray(colors)) return;

            colorInputs.forEach(function (input, index) {
                if (colors[index]) input.value = sanitizeColor(colors[index]);
            });

            setPresetActive(null);
            setPaletteActive(activeButton || null);
            applyConfig();
        };

        var applyColorOrder = function (transform) {
            var colors = colorInputs.map(function (input) {
                return sanitizeColor(input.value);
            });

            colors = transform(colors);
            colorInputs.forEach(function (input, index) {
                if (colors[index]) input.value = colors[index];
            });

            setPresetActive(null);
            setPaletteActive(null);
            applyConfig();
        };

        const renderPreview = createAvatarFramePreview(editor);
        var applyConfig = () => renderPreview(currentConfig());

        editor.querySelectorAll('[data-frame-preset]').forEach(function (button) {
            button.addEventListener('click', function () {
                var config;
                try {
                    config = JSON.parse(button.dataset.framePreset || '{}');
                } catch (error) {
                    config = {};
                }

                applyPresetConfig(config, button);
            });
        });

        var randomizeButton = editor.querySelector('[data-frame-randomize]');
        if (randomizeButton) {
            randomizeButton.addEventListener('click', function () {
                applyPresetConfig({
                    type: randomItem(['linear', 'conic', 'radial']),
                    shape: randomItem(['circle', 'squircle', 'badge', 'soft']),
                    motion: randomItem(['spin', 'pulse', 'sweep', 'still']),
                    pattern: randomItem(['none', 'sparkles', 'pixels', 'stripes']),
                    avatar_effect: randomItem(['none', 'sheen', 'scanner', 'spark']),
                    angle: Math.floor(Math.random() * 360),
                    width: randomItem([4, 5, 6, 7, 8, 9, 10]),
                    glow: randomItem([12, 18, 24, 30, 36, 42]),
                    speed: randomItem([70, 85, 100, 115, 130, 150]),
                    pattern_opacity: randomItem([42, 55, 68, 76, 88, 100]),
                    effect_opacity: randomItem([42, 55, 68, 76, 88, 100]),
                    animated: Math.random() > 0.18,
                    colors: randomItem(randomPalettes),
                }, null);
                setPaletteActive(null);
            });
        }

        var resetButton = editor.querySelector('[data-frame-reset]');
        if (resetButton) {
            resetButton.addEventListener('click', function () {
                applyPresetConfig(defaultFrameConfig, editor.querySelector('[data-frame-preset]'));
                setPaletteActive(editor.querySelector('[data-frame-palette]'));
            });
        }

        editor.querySelectorAll('[data-frame-preview-bg]').forEach(function (button) {
            button.addEventListener('click', function () {
                var mode = button.dataset.framePreviewBg || 'soft';
                editor.querySelectorAll('[data-frame-preview-bg]').forEach(function (item) {
                    item.classList.toggle('is-active', item === button);
                });

                if (!previewStage) return;
                previewBgClasses.forEach(function (className) {
                    previewStage.classList.remove(className);
                });

                if (mode === 'light') {
                    previewStage.classList.add('is-preview-light');
                } else if (mode === 'dark') {
                    previewStage.classList.add('is-preview-dark');
                }
            });
        });

        editor.querySelectorAll('[data-frame-palette]').forEach(function (button) {
            button.addEventListener('click', function () {
                var colors;
                try {
                    colors = JSON.parse(button.dataset.framePalette || '[]');
                } catch (error) {
                    colors = [];
                }

                applyPalette(colors, button);
            });
        });

        var rotateColorsButton = editor.querySelector('[data-frame-rotate-colors]');
        if (rotateColorsButton) {
            rotateColorsButton.addEventListener('click', function () {
                applyColorOrder(function (colors) {
                    return colors.length ? colors.slice(1).concat(colors[0]) : colors;
                });
            });
        }

        var reverseColorsButton = editor.querySelector('[data-frame-reverse-colors]');
        if (reverseColorsButton) {
            reverseColorsButton.addEventListener('click', function () {
                applyColorOrder(function (colors) {
                    return colors.slice().reverse();
                });
            });
        }

        typeInputs.concat(shapeInputs, motionInputs, patternInputs, avatarEffectInputs, colorInputs).forEach(function (input) {
            input.addEventListener('input', function () {
                setPresetActive(null);
                if (input.type === 'color') setPaletteActive(null);
                applyConfig();
            });
            input.addEventListener('change', function () {
                setPresetActive(null);
                if (input.type === 'color') setPaletteActive(null);
                applyConfig();
            });
        });
        [angleInput, widthInput, glowInput, speedInput, patternOpacityInput, effectOpacityInput, animatedInput].forEach(function (input) {
            if (!input) return;
            input.addEventListener('input', function () {
                setPresetActive(null);
                applyConfig();
            });
            input.addEventListener('change', function () {
                setPresetActive(null);
                applyConfig();
            });
        });

        applyConfig();
    });
}
