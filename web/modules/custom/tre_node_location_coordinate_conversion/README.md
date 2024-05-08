# TRE Node Location Coordinate Conversion

## Purpose

Converts location coordinates in nodes into other projections and also maps the
coordinates to geographical areas.

The geographical area data to map the coordinates against is fetched from the
geodata.tampere.fi platform. Because the coordiante points in the data are in a
different projection from the one used by PTV (EPSG:3878 vs EPSG:3067), a
conversion is performed to the area geometry coordiantes before storing the
geometries for comparison.

## Usage

After installing the module, run
`drush  tre_node_location_coordinate_conversion:import-areas`. This will add
the geographical area data into the database table for the mapping queries to
use.

If you want to update the existing content with the mapped areas, use
`drush tre_node_location_coordinate_conversion:fill-in-nodes`.

You can update the geographical area data by rerunning the import-areas
command.

Other than this, the module works automatically: when a node with a geofield
instance (called `field_location`) is updated, the content of that field is
first converted to EPSG:3067 coordinates stored in two separate fields
(`field_epsg_3067_northing`, `field_epsg_3067_easting`). Thereafter the
coordinates in those fields are compared against the geometry data of the
geographical areas, and if a match is found, a term matching the name of the
geometry is used in the `field_geographical_areas` field.

The functionality of the mapping is also used by the PTV integration module to
do the mapping of `place_of_business` nodes based on the locations represented
by `map_point` nodes.
