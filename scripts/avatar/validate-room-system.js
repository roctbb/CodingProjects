#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '../..');
const systemDir = path.join(root, 'public/images/avatar-layers/room-system');
const canvas = 1024;
const requiredSlots = [
  'window_weather',
  'poster_wall',
  'desk_center',
  'safe_under_desk',
  'character',
  'shelf_trophy_1',
  'shelf_trophy_2',
  'shelf_trophy_3',
  'pet_right',
];
const requiredClasses = ['class_05', 'class_06', 'class_07', 'class_08', 'class_09', 'class_10', 'class_11'];
const requiredGenders = ['boy', 'girl'];
const errors = [];

const readJson = (file) => {
  try {
    return JSON.parse(fs.readFileSync(file, 'utf8'));
  } catch (error) {
    errors.push(`Invalid JSON: ${path.relative(root, file)} (${error.message})`);
    return {};
  }
};

const config = readJson(path.join(systemDir, 'config.json'));
const layouts = readJson(path.join(systemDir, 'layouts.json'));

const rel = (file) => path.relative(root, file);
const asset = (file) => path.join(systemDir, file || '');

const fileExists = (file, label) => {
  if (!file || !fs.existsSync(file)) {
    errors.push(`Missing ${label}: ${rel(file || systemDir)}`);
    return false;
  }

  return true;
};

const identify = (file) => {
  const result = spawnSync('magick', [
    'identify',
    '-format',
    '%w|%h|%[channels]|%[opaque]|%[fx:minima.a]|%[fx:maxima.a]|%[fx:mean.a]',
    file,
  ], { encoding: 'utf8' });

  if (result.status !== 0) {
    errors.push(`Cannot identify image: ${rel(file)}`);
    return null;
  }

  const [width, height, channels, opaque, alphaMin, alphaMax, alphaMean] = result.stdout.trim().split('|');
  return {
    width: Number(width),
    height: Number(height),
    channels,
    opaque,
    alphaMin: Number(alphaMin),
    alphaMax: Number(alphaMax),
    alphaMean: Number(alphaMean),
  };
};

const identifyCropAlpha = (file, slot) => {
  const result = spawnSync('magick', [
    file,
    '-crop',
    `${Number(slot.width)}x${Number(slot.height)}+${Number(slot.x)}+${Number(slot.y)}`,
    '+repage',
    '-format',
    '%[fx:minima.a]|%[fx:mean.a]|%[fx:maxima.a]',
    'info:',
  ], { encoding: 'utf8' });

  if (result.status !== 0) {
    errors.push(`Cannot inspect slot alpha: ${rel(file)}`);
    return null;
  }

  const [alphaMin, alphaMean, alphaMax] = result.stdout.trim().split('|').map(Number);
  return { alphaMin, alphaMean, alphaMax };
};

const checkImage = (file, label, { fullCanvas = false, allowedDimensions = null, requireAlpha = false, requireTransparency = false } = {}) => {
  if (!fileExists(file, label)) return;
  const info = identify(file);
  if (!info) return;

  if (fullCanvas && (info.width !== canvas || info.height !== canvas)) {
    errors.push(`${label} must be ${canvas}x${canvas}: ${rel(file)} is ${info.width}x${info.height}`);
  }

  if (Array.isArray(allowedDimensions)) {
    const matches = allowedDimensions.some(([width, height]) => info.width === width && info.height === height);
    if (!matches) {
      errors.push(`${label} must be one of ${allowedDimensions.map(([width, height]) => `${width}x${height}`).join(', ')}: ${rel(file)} is ${info.width}x${info.height}`);
    }
  }

  if (requireAlpha && !info.channels.includes('a')) {
    errors.push(`${label} must have alpha channel: ${rel(file)} has ${info.channels}`);
  }

  if (requireTransparency && (!Number.isFinite(info.alphaMin) || info.alphaMin >= 1 || info.alphaMean >= 0.99)) {
    errors.push(`${label} must contain transparent pixels for dynamic layers: ${rel(file)} alpha min=${info.alphaMin} mean=${info.alphaMean}`);
  }
};

const checkDynamicCutout = (roomKey, file, slotKey, slot) => {
  if (!slot) return;
  if (!fileExists(file, `room ${roomKey}`)) return;

  const stats = identifyCropAlpha(file, slot);
  if (!stats) return;

  if (!Number.isFinite(stats.alphaMin) || !Number.isFinite(stats.alphaMean)) {
    errors.push(`Invalid alpha stats for ${roomKey}.${slotKey}: ${JSON.stringify(stats)}`);
    return;
  }

  const ok = stats.alphaMin <= 0.05 && stats.alphaMean <= 0.75;
  if (!ok) {
    errors.push(`${roomKey}.${slotKey} must substantially overlap a transparent cutout: alpha min=${stats.alphaMin} mean=${stats.alphaMean}`);
  }
};

const checkSafeCoinOverlay = (entry) => {
  const file = asset(entry.file);
  if (!fileExists(file, `safes.${entry.key}`)) return;

  const info = identify(file);
  if (!info) return;

  if (!info.channels.includes('a')) {
    errors.push(`safe overlay ${entry.key} must have alpha channel: ${rel(file)} has ${info.channels}`);
  }

  if (info.alphaMean > 0.5) {
    errors.push(`safe overlay ${entry.key} must contain coins only, not a baked safe/body: ${rel(file)} alpha mean=${info.alphaMean}`);
  }

  if (entry.key === 'safe_empty' && info.alphaMean > 0.01) {
    errors.push(`safe_empty must be an empty transparent overlay: ${rel(file)} alpha mean=${info.alphaMean}`);
  }

  if (entry.key !== 'safe_empty' && info.alphaMax <= 0) {
    errors.push(`safe overlay ${entry.key} must contain visible coins: ${rel(file)}`);
  }
};

const checkSlot = (roomKey, slotKey, slot) => {
  if (!slot) {
    errors.push(`Missing slot ${slotKey} for ${roomKey}`);
    return;
  }

  const x = Number(slot.x);
  const y = Number(slot.y);
  const width = Number(slot.width);
  const height = Number(slot.height);

  if (![x, y, width, height].every(Number.isFinite)) {
    errors.push(`Invalid numeric geometry for ${roomKey}.${slotKey}`);
    return;
  }

  if (x < 0 || y < 0 || width <= 0 || height <= 0 || x + width > canvas || y + height > canvas) {
    errors.push(`Slot out of canvas for ${roomKey}.${slotKey}: ${JSON.stringify(slot)}`);
  }
};

if (!Array.isArray(config.rooms) || config.rooms.length !== 17) {
  errors.push(`Expected 17 rooms, found ${Array.isArray(config.rooms) ? config.rooms.length : 'none'}`);
}

const seasons = Array.isArray(config.seasons) ? config.seasons : [];
const weatherStates = Array.isArray(config.weatherStates) ? config.weatherStates : [];
if (seasons.length !== 4) {
  errors.push(`Expected 4 seasons, found ${seasons.length || 'none'}`);
}
if (weatherStates.length !== 5) {
  errors.push(`Expected 5 weather states, found ${weatherStates.length || 'none'}`);
}

let previousProgressionLevel = 0;
(config.rooms || []).forEach((room) => {
  if (!room.key) {
    errors.push('Room without key');
    return;
  }

  const progression = room.visualProgression || {};
  const progressionLevel = Number(progression.level);
  if (!Number.isInteger(progressionLevel) || progressionLevel <= previousProgressionLevel) {
    errors.push(`Room ${room.key} must have a strictly increasing visualProgression.level after ${previousProgressionLevel}`);
  }
  previousProgressionLevel = Number.isFinite(progressionLevel) ? progressionLevel : previousProgressionLevel;

  if (progression.safeOpen !== true) {
    errors.push(`Room ${room.key} must mark visualProgression.safeOpen=true; the safe is part of the room and must stay open for coin overlays`);
  }

  if (!progression.summary || typeof progression.summary !== 'string') {
    errors.push(`Room ${room.key} must describe its visual progression summary`);
  }

  checkImage(asset(room.file), `room ${room.key}`, { fullCanvas: true, requireAlpha: true, requireTransparency: true });
  const layout = layouts[room.key];
  if (!layout) {
    errors.push(`Missing layout for ${room.key}`);
    return;
  }

  requiredSlots.forEach((slotKey) => checkSlot(room.key, slotKey, layout[slotKey]));
  checkDynamicCutout(room.key, asset(room.file), 'window_weather', layout.window_weather);
  checkDynamicCutout(room.key, asset(room.file), 'poster_wall', layout.poster_wall);

  const weather = layout._weather || {};
  ['scale', 'cropX', 'cropY', 'x', 'y', 'width', 'height'].forEach((key) => {
    if (!Number.isFinite(Number(weather[key]))) {
      errors.push(`Missing numeric _weather.${key} for ${room.key}`);
    }
  });
  if (Number.isFinite(Number(weather.x)) && (Number(weather.x) < -2048 || Number(weather.x) > 2048)) {
    errors.push(`Weather placement x is out of editor bounds for ${room.key}: ${weather.x}`);
  }
  if (Number.isFinite(Number(weather.y)) && (Number(weather.y) < -2048 || Number(weather.y) > 2048)) {
    errors.push(`Weather placement y is out of editor bounds for ${room.key}: ${weather.y}`);
  }
  if (Number.isFinite(Number(weather.width)) && (Number(weather.width) < 50 || Number(weather.width) > 3072)) {
    errors.push(`Weather placement width is out of editor bounds for ${room.key}: ${weather.width}`);
  }
  if (Number.isFinite(Number(weather.height)) && (Number(weather.height) < 50 || Number(weather.height) > 3072)) {
    errors.push(`Weather placement height is out of editor bounds for ${room.key}: ${weather.height}`);
  }
});

const layerSectionRules = {
  items: { requireAlpha: true, requireTransparency: true },
  pets: { requireAlpha: true, requireTransparency: true },
  safes: { requireAlpha: true },
};

['seasons', 'weatherStates', 'weather', 'posters', 'items', 'pets', 'safes'].forEach((section) => {
  if (!Array.isArray(config[section]) || config[section].length === 0) {
    errors.push(`Missing config section: ${section}`);
    return;
  }

  const keys = new Set();
  config[section].forEach((entry) => checkImage(asset(entry.file), `${section}.${entry.key}`, layerSectionRules[section] || {}));
  config[section].forEach((entry) => {
    if (!entry.key) {
      errors.push(`Missing key in config section: ${section}`);
      return;
    }

    if (keys.has(entry.key)) {
      errors.push(`Duplicate key in ${section}: ${entry.key}`);
    }

    keys.add(entry.key);
  });

  if (section === 'safes') {
    config[section].forEach(checkSafeCoinOverlay);
  }
});

const weatherKeys = new Set((config.weather || []).map((entry) => entry.key));
seasons.forEach((season) => {
  weatherStates.forEach((state) => {
    const key = `${season.key}_${state.key}`;
    if (!weatherKeys.has(key)) {
      errors.push(`Missing seasonal weather asset: ${key}`);
    }
  });
});

requiredGenders.forEach((gender) => {
  requiredClasses.forEach((classKey) => {
    checkImage(asset(config.characters?.[gender]?.[classKey]), `character ${gender}.${classKey}`, {
      allowedDimensions: [[1024, 1024], [1024, 1536]],
      requireAlpha: true,
      requireTransparency: true,
    });
  });
});

if (errors.length) {
  console.error(errors.map((error) => `- ${error}`).join('\n'));
  process.exit(1);
}

console.log([
  'Room system OK',
  `rooms: ${(config.rooms || []).length}`,
  `weather: ${(config.weather || []).length}`,
  `posters: ${(config.posters || []).length}`,
  `characters: ${requiredGenders.length * requiredClasses.length}`,
].join('\n'));
