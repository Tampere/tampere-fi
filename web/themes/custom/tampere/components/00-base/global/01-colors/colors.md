# Colors
All available color variables are found in `_color-vars.scss` and color palettes in `_colors-used.scss`. CSS variables should be preferred in components to support the different color palettes found in `_colors-used.scss`. See the `clr()` function in `_colors-used.scss` for more details.

## Color palettes
Palette colors are stored in maps like the default colors (`$defaultColors`), and they are used to replace a subset of the default colors.

### Colors to replace
Not all palettes behave exactly the same, but the main principle so far has been that the following colors are replaced with new ones in palettes (some palettes only replace a few of these so you do not have to necessarily define all of these in the new palette):

 - secondary
 - accent
 - accent-tertiary
 - accent-tertiary-transparent
 - background-secondary
 - accent-secondary
 - accent-secondary-transparent
 - muted
 - muted-transparent

You can see the current behavior, for example, by going to the Storybook color palette page in your browser and switching the active palette (there's a theme toggle on the top toolbar). You will notice what colors change and what do not. There is also a Minisite example page at Pages > Content Types > Minisite Collection Page. Switch the active palette with the theme toggle to get a better idea what the different components look like with a specific palette.

See [color guide](color-guide.png) for a visual guide of the base for all color palettes. When new color palette requests come in the colors have been displayed in a similar grid formation, see [example palette request](example-palette-request.png). In this example, you would set the `secondary` color in the new palette to `$color-light-green` (i.e. `#abc872`), `accent` to `$color-dark-blue` (i.e. `#29549a`), etc.

Note that the same color can be used for different purposes on the site, and the colors shouldn't be replaced just based on their hex value or matching SASS variable.

For example, given a situation like this:
```
// _color-vars.scss
$color-light-gray: #f1eeeb;

// _colors-used.scss
$defaultColors: (
  accent-secondary: $color-light-gray,
  inactive: $color-light-gray,
);
```
If the color `#f1eeeb` should be replaced in a new palette with a different color, this does not mean that the default color `inactive` should be changed. Only replace `accent-secondary` in the new palette (unless otherwise specified with the client).

### Adding new color palettes
Create a new map for the palette in `_colors-used.scss`. Previously the naming scheme has followed the following pattern: `$palette-{number}`. For example:
```
$palette-1: (
  secondary: $color-pink,
  accent: $color-yellow,
);
```
After this, add the new palette to the `$minisitePalettes` list so that the new colors can be used in the theme when the palette is active.

Ensure that the new color combinations have sufficient contrast whenever they are used. Some palettes have defined extra colors for this purpose (e.g. 'primary-text-on-background-secondary', 'primary-text-on-tertiary').

#### Storybook
In order to use the new palette in Storybook, add an entry to the themes list in `/themes/custom/tampere/.storybook/preview.js`. The color can be anything, but previously the `secondary` color for the palette has been used. The active theme can be selected from the Storybook toolbar.
