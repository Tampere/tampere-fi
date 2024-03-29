---
title: Icons
---

### How to use icons

We are using an SVG sprite generator (details [here](https://www.npmjs.com/package/svg-sprite-loader)), which automatically takes individual SVGs from `/images/icon-sets/` and generates a sprite for each directory (e.g. `/dist/icons.svg`). Webpack will automatically add your individual SVGs to the sprites.

New icons should be added to `main-site-icons` unless they should be available as icon options in the icon fields on the site (these should go into `icons`). When adding new icons, avoid hard-coding the color directly into the `svg` files and instead use the `currentColor` value (e.g. instead of `stroke="#fff"` use `stroke="currentColor"`). The icon colors can differ based on the active color palette and they are changed in styles.

**Usage**

The SVG component is found here:
`/components/01-atoms/04-images/icons/_icon.twig`.
See available variables in that file
as well as instructions for Drupal. Examples of usage below:

Simple: (no BEM renaming)

```
{% include "@atoms/04-images/icons/_icon.twig" with {
  icon_name: 'menu',
} %}
```

... creates...

```
<svg class="icon">
  <use xmlns:xlink="http://www.w3.org/1999/xlink"
  xlink:href="/icons.svg#src--menu"></use>
</svg>
```

Complex (BEM classes):

```
{% include "@atoms/04-images/icons/_icon.twig" with {
  icon_base_class: 'toggle',
  icon_blockname: 'main-nav',
  icon_name: 'menu',
} %}
```

... creates...

```
<svg class="main-nav__toggle">
  <use xmlns:xlink="http://www.w3.org/1999/xlink"
  xlink:href="/icons.svg#src--menu"></use>
</svg>
```

**Directories**

You can define the icon directory in the Twig template which calls the icon like this:

```
{% if items %}
  {% set directory = "/themes/custom/tampere/" %}
{% else %}
  {% set directory = "../../../../" %}
{% endif %}
```
