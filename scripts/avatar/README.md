# Room Runtime Assets

The room system keeps only production assets in the repository:

- `public/images/avatar-layers/room-system/rooms`
- `public/images/avatar-layers/room-system/weather`
- `public/images/avatar-layers/room-system/posters/default.png`
- `public/images/avatar-layers/room-system/items`
- `public/images/avatar-layers/room-system/pets`
- `public/images/avatar-layers/room-system/characters`
- `public/images/avatar-layers/room-system/safes`
- `public/images/avatar-layers/room-system/config.json`
- `public/images/avatar-layers/room-system/layouts.json`

Generated source sheets, prompt packs, preview matrices, and room source images
are intentionally not committed. If assets are regenerated later, import only
the final PNG layers required by production.

## Checks

```bash
rtk npm run avatar:room-system:check
```

This verifies weather assets, validates the runtime room manifest and PNGs, and
runs the focused Laravel room tests.
