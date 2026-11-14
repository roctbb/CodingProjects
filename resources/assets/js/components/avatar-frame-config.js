// Frame artwork and pricing are independent of the surrounding UI theme.
export const defaultFrameConfig = {
    type: 'linear',
    shape: 'circle',
    motion: 'spin',
    pattern: 'sparkles',
    avatar_effect: 'sheen',
    angle: 135,
    width: 6,
    glow: 28,
    speed: 100,
    pattern_opacity: 72,
    effect_opacity: 70,
    animated: true,
    colors: ['#22d3ee', '#8b5cf6', '#f97316', '#22c55e'],
};
export const randomPalettes = [
    ['#22d3ee', '#8b5cf6', '#f97316', '#22c55e'],
    ['#f43f5e', '#f59e0b', '#22c55e', '#38bdf8'],
    ['#0f172a', '#166534', '#22c55e', '#84cc16'],
    ['#fef3c7', '#f59e0b', '#b45309', '#fde68a'],
    ['#60a5fa', '#a78bfa', '#f472b6', '#fb7185'],
    ['#14b8a6', '#22c55e', '#eab308', '#f97316'],
];

export function sanitizeColor(value) {
    return /^#[0-9a-f]{6}$/i.test(value || '') ? value.toLowerCase() : '#22d3ee';
}

export function gradientFor(config) {
    var colors = config.colors.length >= 2 ? config.colors : ['#22d3ee', '#8b5cf6'];
    if (config.type === 'conic') {
        return 'conic-gradient(from ' + config.angle + 'deg, ' + colors.join(', ') + ', ' + colors[0] + ')';
    }

    if (config.type === 'radial') {
        return 'radial-gradient(circle, ' + colors.join(', ') + ')';
    }

    return 'linear-gradient(' + config.angle + 'deg, ' + colors.join(', ') + ')';
}

export function shapeFor(shape) {
    var shapes = {
        circle: ['999px', '999px'],
        squircle: ['1.35rem', '1.1rem'],
        badge: ['1.6rem 0.8rem', '1.25rem 0.62rem'],
        soft: ['1rem', '0.8rem'],
    };

    return shapes[shape] || shapes.circle;
}

export function patternFor(pattern) {
    var patterns = {
        sparkles: 'radial-gradient(circle at 50% 6%, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 0, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 0.16rem, transparent 0.19rem), radial-gradient(circle at 94% 34%, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 0, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 0.13rem, transparent 0.17rem), radial-gradient(circle at 76% 94%, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 0, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 0.15rem, transparent 0.18rem), radial-gradient(circle at 9% 66%, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 0, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 0.12rem, transparent 0.16rem), radial-gradient(circle at 13% 18%, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 0, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 0.11rem, transparent 0.15rem)',
        pixels: 'linear-gradient(90deg, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 50%, transparent 0) 0 0 / 0.55rem 0.55rem, linear-gradient(rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 50%, transparent 0) 0 0 / 0.55rem 0.55rem',
        stripes: 'repeating-linear-gradient(135deg, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 0 0.16rem, transparent 0.16rem 0.48rem)',
    };

    return patterns[pattern] || 'none';
}

export function frameAnimationFor(config) {
    if (!config.animated) return 'none';
    var durationFor = function (seconds) {
        return Math.round((seconds * 100 / config.speed) * 100) / 100 + 's';
    };

    var animations = {
        spin: 'profile-avatar-rainbow-spin ' + durationFor(5.6) + ' linear infinite',
        pulse: 'profile-avatar-frame-pulse ' + durationFor(2.8) + ' ease-in-out infinite',
        sweep: 'profile-avatar-frame-sweep ' + durationFor(3.4) + ' linear infinite',
        still: 'none',
    };

    return animations[config.motion] || animations.spin;
}

export function avatarEffectFor(config) {
    if (!config.animated || config.avatarEffect === 'none') {
        return ['transparent', 'none', '0'];
    }
    var durationFor = function (seconds) {
        return Math.round((seconds * 100 / config.speed) * 100) / 100 + 's';
    };

    var effects = {
        sheen: ['linear-gradient(115deg, transparent 18%, rgba(255, 255, 255, 0.62) 45%, transparent 72%)', 'profile-avatar-sheen ' + durationFor(3.6) + ' ease-in-out infinite'],
        scanner: ['linear-gradient(180deg, transparent 28%, rgba(34, 211, 238, 0.52) 50%, transparent 72%)', 'profile-avatar-scanner ' + durationFor(2.8) + ' ease-in-out infinite'],
        spark: ['radial-gradient(circle at 25% 35%, rgba(255, 255, 255, 0.86) 0 0.12rem, transparent 0.14rem), radial-gradient(circle at 68% 58%, rgba(255, 255, 255, 0.72) 0 0.1rem, transparent 0.12rem)', 'profile-avatar-spark ' + durationFor(2.4) + ' ease-in-out infinite'],
    };

    var effect = effects[config.avatarEffect] || effects.sheen;
    return effect.concat(String(Math.round(config.effectOpacity) / 100));
}


export function normalizeFrameConfig(value) {
    return {
        type: value.type,
        shape: value.shape,
        motion: value.motion,
        pattern: value.pattern,
        avatarEffect: value.avatarEffect,
        colors: value.colors.map(sanitizeColor).filter(Boolean),
        angle: Math.max(0, Math.min(359, parseInt(value.angle, 10) || 0)),
        width: Math.max(2, Math.min(14, parseInt(value.width, 10) || 6)),
        glow: Math.max(0, Math.min(52, parseInt(value.glow, 10) || 0)),
        speed: Math.max(60, Math.min(180, parseInt(value.speed, 10) || 100)),
        patternOpacity: Math.max(20, Math.min(100, parseInt(value.patternOpacity, 10) || 72)),
        effectOpacity: Math.max(20, Math.min(100, parseInt(value.effectOpacity, 10) || 70)),
        animated: value.animated,
    };
}

export function frameCostFor(config, pricing) {
    if (!pricing) return 0;
    const staticCost = parseInt(pricing.frameStaticCost || '100', 10) || 100;
    const animatedCost = parseInt(pricing.frameAnimatedCost || '150', 10) || 150;
    return config.animated ? animatedCost : staticCost;
}
