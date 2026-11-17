#!/usr/bin/env python3
"""Pack generated cutouts and describe their physical joints; preserve source alpha.

python3 scripts/avatar/build-screen-pet-rigs.py --source-dir output/design/pet-rigs-v1
Source files and exact imagegen prompts are recorded in docs/screen-pet-rig-prompts.json.
"""
import argparse
import json
from pathlib import Path

from PIL import Image, ImageFilter

ROOT = Path(__file__).resolve().parents[2]
PUBLIC = ROOT / 'public/images/screen-pets'
CELL = 256


def content(image):
    bounds = image.getchannel('A').point(lambda a: 255 if a >= 160 else 0).getbbox()
    x0, y0, x1, y1 = bounds
    return image.crop((max(0, x0-4), max(0, y0-4), min(image.width, x1+4), min(image.height, y1+4)))


def fit(image, max_width, max_height):
    scale = min(max_width / image.width, max_height / image.height)
    return image.resize((round(image.width * scale), round(image.height * scale)), Image.Resampling.LANCZOS)


def tile_body(image, width, height, bottom):
    image = fit(content(image), width, height)
    tile = Image.new('RGBA', (CELL, CELL))
    tile.paste(image, (round((CELL-image.width)/2), bottom-image.height))
    return tile


def tile_foot(image, max_width, max_height, sole, view):
    image = fit(content(image), max_width, max_height)
    ankle_x = image.width * ({'right': .28, 'left': .72, 'front': .5}[view])
    tile = Image.new('RGBA', (CELL, CELL))
    tile.paste(image, (round(128-ankle_x), 128+sole-image.height))
    return tile


def tile_bone(image, length, width):
    image = content(image).resize((width, length+width), Image.Resampling.LANCZOS)
    tile = Image.new('RGBA', (CELL, CELL))
    tile.paste(image, (round(128-width/2), round(128-width/2)))
    return tile


def split_sheet(path, rows=2, columns=4):
    image = Image.open(path).convert('RGBA')
    return [image.crop((round(col*image.width/columns), round(row*image.height/rows),
                        round((col+1)*image.width/columns), round((row+1)*image.height/rows)))
            for row in range(rows) for col in range(columns)]


def add_turn(tiles, path, body_bounds):
    first = len(tiles)
    for part in split_sheet(path, rows=3)[1:-1]:
        tiles.append(tile_body(part,*body_bounds))
    # Exact standing endpoints avoid a change of model when the turn begins/ends.
    return [0]+list(range(first,first+10))+[2]


def register_mount(tile, desired):
    # Register into the actual opaque torso, not the transparent space left by
    # removing its legs. Keep a four-pixel overlap for the upper limb under it.
    mask = tile.getchannel('A').point(lambda a: 255 if a >= 230 else 0).filter(ImageFilter.MinFilter(9))
    x, y = desired
    candidates = [(2*(xx-x)**2+(yy-y)**2,xx,yy)
                  for yy in range(max(0,round(y)-70),min(256,round(y)+1))
                  for xx in range(max(0,round(x)-32),min(256,round(x)+33)) if mask.getpixel((xx,yy))]
    if not candidates:
        raise ValueError('No opaque body mount near '+str(desired))
    _,xx,yy = min(candidates)
    return [xx,yy]


def interpolate_mount(leg, yaw):
    views = ('right','front') if yaw < .5 else ('front','left')
    t = yaw*2 if yaw < .5 else yaw*2-1
    t = t*t*(3-2*t)
    return [leg[views[0]][i]+(leg[views[1]][i]-leg[views[0]][i])*t for i in range(2)]


def register_rig(tiles, rig):
    for item in rig['legs']:
        item['feet'] = {view:[item[view][0],rig['ankle']] for view in ['right','front','left']}
        if len(rig['legs']) >= 3 and not item['frontLeg']:
            item['feet']['front'][1] -= 12 if rig['material']=='robot' else 8
        for view in ['right','front','left']:
            item[view] = register_mount(tiles[rig['body'][view]],item[view])
    rig['turnHips'] = [[register_mount(tiles[frame],interpolate_mount(item,index/(len(rig['turnBody'])-1)))
                        for item in rig['legs']] for index,frame in enumerate(rig['turnBody'])]
    # Adjust anatomical lengths once, keeping every segment fixed during motion.
    # Standing/stride targets stay where they were, independently of body mounts.
    import math
    for item in rig['legs']:
        reach = 0
        for view in ['right','left']:
            for advance in [-rig['stride']*rig['duty']/2,rig['stride']*rig['duty']/2]:
                reach = max(reach,math.hypot(item['feet'][view][0]+advance-item[view][0],rig['ankle']-item[view][1]))
        length = math.ceil(reach/2)+1
        item['upper'] = max(item['upper'],length)
        item['lower'] = max(item['lower'],length)


def leg(x, y, phase, upper, lower, *, far=False, front=False, bend=-1, front_x=None, front_y=None):
    return {'right': [x,y], 'front': [front_x if front_x is not None else (148 if far else 108), front_y if front_y is not None else y],
            'left': [256-x,y], 'phase': phase, 'upper': upper, 'lower': lower, 'bend': bend, 'far': far, 'frontLeg': front}


def save(key, name, tiles, rig):
    register_rig(tiles,rig)
    atlas = Image.new('RGBA', (CELL*4, CELL*((len(tiles)+3)//4)))
    for index, tile in enumerate(tiles):
        atlas.paste(tile, (index%4*CELL, index//4*CELL))
    stem = key+'-rig-v1'
    atlas.save(PUBLIC/(stem+'.webp'), quality=92, method=6)
    frames = [{'x': i%4*CELL, 'y': i//4*CELL} for i in range(len(tiles))]
    data = {'name': name, 'atlas': 'images/screen-pets/'+stem+'.webp', 'columns': 4, 'rows': atlas.height//CELL,
            'cell': CELL, 'baseline': 253, 'frames': frames, 'motion': 'rig', 'size': 96, 'mobileSize': 80, 'visitPause': 1800, 'rig': rig}
    (PUBLIC/(stem+'.json')).write_text(json.dumps(data, ensure_ascii=False, separators=(',',':'))+'\n')
    print(stem, atlas.size, (PUBLIC/(stem+'.webp')).stat().st_size)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--source-dir', required=True, type=Path)
    source = parser.parse_args().source_dir
    specs = [
        {'key': 'brr_brr_patapim', 'source': 'forest-clean', 'name': 'Брр Брр Потапим', 'body': (180,170,180),
         'foot': (104,68,36), 'upper': (36,23), 'lower': (36,20), 'stride': 64, 'duty': .7, 'lift': 24, 'bob': 2,
         'cadence': 1320, 'turn': 1050, 'legs': [leg(108,164,0,36,36),leg(146,164,.5,36,36,far=True)]},
        {'key': 'tralalelo_tralala', 'source': 'shark', 'name': 'Тралалело Тралала', 'body': (228,150,178),
         'foot': (58,34,20), 'upper': (35,22), 'lower': (35,18), 'stride': 72, 'duty': .74, 'lift': 27, 'bob': 1.7,
         'cadence': 900, 'turn': 1050, 'legs': [leg(178,164,1/3,39,39,far=True,front=True,front_x=154,front_y=166),
             leg(74,174,2/3,35,35,front_x=128,front_y=170), leg(155,174,0,35,35,front=True,front_x=104,front_y=176)]},
        {'key': 'tung_tung_tung_sahur', 'source': 'wood', 'name': 'Тунг Тунг Тунг Сахур', 'body': (160,160,172),
         'foot': (46,30,17), 'upper': (38,14), 'lower': (38,12), 'stride': 64, 'duty': .68, 'lift': 27, 'bob': 1.4,
         'cadence': 1080, 'turn': 900, 'legs': [leg(111,165,0,38,38),leg(145,165,.5,38,38,far=True)]},
    ]
    for spec in specs:
        parts = split_sheet(source/(spec['source']+'-source.png'))
        tiles = [tile_body(image,*spec['body']) for image in parts[:3]]
        tiles.append(tile_foot(parts[3],*spec['foot'],'right'))
        tiles.append(tile_bone(parts[4],*spec['upper']))
        tiles.append(tile_bone(parts[5],*spec['lower']))
        tiles.append(tile_foot(parts[6],*spec['foot'],'left'))
        tiles.append(tile_foot(parts[7],*spec['foot'],'front'))
        rig = {'version': 1, 'material': 'sprites', 'body': {'right':0,'front':1,'left':2},
               'feet': {'right':3,'left':6,'front':7}, 'ankle': 253-spec['foot'][2],
               'bones': {'upper':4,'lower':5,'upperLength':spec['upper'][0],'lowerLength':spec['lower'][0]},
               'legs':spec['legs'], 'stride':spec['stride'], 'duty':spec['duty'], 'lift':spec['lift'], 'bob':spec['bob'],
               'cadence':spec['cadence'], 'turnDuration':spec['turn'], 'repositionLift':12}
        turn_source = 'forest' if spec['source']=='forest-clean' else spec['source']
        turn_bounds = (228,136,178) if turn_source=='shark' else spec['body']
        rig['turnBody'] = add_turn(tiles,source/(turn_source+'-turn-source.png'),turn_bounds)
        save(spec['key'],spec['name'],tiles,rig)

    # The right torso is the approved prototype, with its exact dimensions and registration.
    tiles = []
    for side, offset in [('right',5),('left',-5)]:
        image = Image.open(source/('robot-'+side+'-source.png')).convert('RGBA')
        image = image.resize((256,round(image.height*256/image.width)),Image.Resampling.LANCZOS)
        tile = Image.new('RGBA',(CELL,CELL)); tile.paste(image,(offset,53)); tiles.append(tile)
    views = Image.open(source/'robot-views-source.png').convert('RGBA')
    front = content(views.crop((views.width//2,0,views.width,views.height//2)))
    front = fit(front,210,140)
    tile = Image.new('RGBA',(CELL,CELL));tile.paste(front,(round((256-front.width)/2),205-front.height))
    tiles.insert(1,tile)
    turn_body = add_turn(tiles,source/'robot-turn-source.png',(244,136,205))
    save('learning_bot','Робопёс',tiles,{
        'version':1,'material':'robot','body':{'right':0,'front':1,'left':2},'ankle':245,
        'stride':72,'duty':.78,'lift':26,'bob':1.3,'cadence':1050,'turnDuration':1200,'repositionLift':12,'turnBody':turn_body,
        'legs': [leg(176,178,0,38,38,far=True,front=True,bend=1,front_x=171,front_y=186),
                 leg(92,178,.25,38,38,far=True,bend=-1,front_x=156,front_y=198),
                 leg(165,186,.5,35,35,front=True,bend=1,front_x=85,front_y=186),
                 leg(80,186,.75,35,35,bend=-1,front_x=100,front_y=198)]})

    # Keep Cheremsha's approved 16-pose hop and timing; append only seated turn views.
    original = Image.open(PUBLIC/'cheremsha-hop-v1.webp').convert('RGBA')
    data = json.loads((PUBLIC/'cheremsha-hop-v1.json').read_text())
    atlas = Image.new('RGBA',(CELL*4,CELL*5)); atlas.paste(original,(0,0))
    for index, part in enumerate(split_sheet(source/'cheremsha-turn-source.png',rows=2,columns=2)):
        atlas.paste(tile_body(part,240,218,238),(index*CELL,CELL*4))
    atlas.save(PUBLIC/'cheremsha-hop-v2.webp',quality=90,method=6)
    side = dict(data['frames'][0],flip=False)
    turn = [side]+[{'x':i*CELL,'y':CELL*4,'flip':False} for i in range(4)]
    turn += [dict(turn[1],flip=True),dict(side,flip=True)]
    data.update(atlas='images/screen-pets/cheremsha-hop-v2.webp',rows=5,turnDuration=760,
                turn={'right':turn,'left':[dict(frame,flip=not frame['flip']) for frame in turn]})
    (PUBLIC/'cheremsha-hop-v2.json').write_text(json.dumps(data,ensure_ascii=False,separators=(',',':'))+'\n')
    print('cheremsha-hop-v2',atlas.size,(PUBLIC/'cheremsha-hop-v2.webp').stat().st_size)


if __name__ == '__main__':
    main()
