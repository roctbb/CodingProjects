#!/usr/bin/env python3
"""Check actual atlas alpha at every body mount and limb pivot, including turns."""
from pathlib import Path
import json

from PIL import Image

ROOT = Path(__file__).resolve().parents[2]
checked = 0
for name in ['learning_bot','brr_brr_patapim','tralalelo_tralala','tung_tung_tung_sahur']:
    data = json.loads((ROOT/'public/images/screen-pets'/(name+'-rig-v1.json')).read_text())
    alpha = Image.open(ROOT/'public'/data['atlas']).getchannel('A')
    rig = data['rig']

    def check(index, position, clearance=0):
        global checked
        frame = data['frames'][index]
        x,y = position
        values = [alpha.getpixel((frame['x']+x+dx,frame['y']+y+dy))
                  for dx in range(-clearance,clearance+1) for dy in range(-clearance,clearance+1)]
        if min(values) < 230:
            raise AssertionError(f'{name}: disconnected mount in frame {index} at {position}, alpha {min(values)}')
        checked += 1

    for view,index in rig['body'].items():
        for item in rig['legs']:
            check(index,item[view],clearance=4)
    for index,mounts in zip(rig['turnBody'],rig['turnHips']):
        for mount in mounts:
            check(index,mount,clearance=4)
    if rig['material']=='sprites':
        for part in ['upper','lower']:
            check(rig['bones'][part],[128,128])
            check(rig['bones'][part],[128,128+rig['bones'][part+'Length']])
        for index in rig['feet'].values():
            check(index,[128,128])
print(f'Passed: {checked} body mounts and limb pivots are opaque and overlap correctly.')
