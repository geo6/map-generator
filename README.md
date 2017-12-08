# Map Generator

# Tools

## Mapfile generator

Usage :
```
php generate-map.php
  -m, --map=MAPFILE                   Path to original mapfile
  -d, --directory=DIRECTORY           Path to the directory where the mapfiles will be written.
                                      Default: same directory as original mapfile
  --mode=MODE                         Values: municipality|grid. Default: municipality
  -r, --resolution=RESOLUTION         Image resolution (in dpi). Default: 72
  -u, --size_units=UNITS              Values: mm|cm|pxl. Default: pxl
  -x, --size_x=WIDTH                  Image width (in size_units)
  -y, --size_y=HEIGHT                 Image height (in size_units)
  -e, --extent="XMIN YMIN XMAX MAX"   Map extent (in longitude, latitude).
                                      Only available for grid mode.
  -s, --scale_denom                   Map scale denominator. Default: 15000.
                                      Only available for grid mode or municipality mode with fixed scale
```

## Image generator (PNG)

Usage :
```
php generate-png.php
  -d, --directory=DIRECTORY           Path to the directory where the mapfiles are and result images will be written.
                                      Default: same directory as original mapfile
  --nis=NIS1,NIS2,...                 List of NIS codes to generate. Default: ALL
  -c, --copyright=COPYRIGHT           Copyright to add on the image
```

## Modes

3 modes are available :

### Municipality : fixed image size

The image width and height are fixed defined by the `-u`, `-x`, and `-y` options. The map scale will be variable.

    php generate-map.php --map=mapfile.map

### Municipality : fixed scale

The map scale is fixed by the `-s` option. The image width and height will be variable.

    php generate-map.php --map=mapfile.map -x 0 -y 0

### Grid

    php generate-map.php --map=mapfile.map --mode=grid --extent="5.026033 50.971741 5.078047 50.996272"
