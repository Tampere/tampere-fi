# Tampere 2.0 (Emulsify Exove): Customizable style guide + Storybook Development + Drupal 8 theme

Tampere 2.0 is a full Design System encompassing both a Storybook/Webpack development environment, a customizable Styleguide powered by GatsbyJS (coming soon), and a Drupal 8 starterkit theme.

This version was forked from the [beta1 version of Emulsify](https://github.com/emulsify-ds/emulsify-drupal/tree/v1.0.0-beta.1)(the hash of the latest commit taken: 8e59b0a)

## Installations

### Requirements

1.  [PHP 7.1](http://www.php.net/)
2.  [Node (we recommend NVM)](https://github.com/creationix/nvm)
    * Supported version nodejs 14. You can switch to the recommended version with `nvm use` after you have installed it.
    * Prior to 2.0.1 supported version is nodejs 10.
3.  [Webpack](https://webpack.js.org/)
4.  [Composer](https://getcomposer.org/)
5.  [Yarn](https://github.com/yarnpkg/yarn)
#### Enabling Browser-sync

Before you can use Browser-sync and hot reloading while developing, you need to change the project address in the file `webpack/webpack-dev.js`. Replace `PROJECT-DOMAIN.test` with the actual domain name of your local development environment, e.g `http://codebase.l.test`.

For a docker-based setup more changes might be needed, e.g. configure and expose other ports.

More information from [Browser-sync documentation](https://www.browsersync.io/docs/).

### Starting development

The `yarn develop` compiles everything and watches for changes.

This combines 3 tasks which can be run independently as well:
* `yarn webpack` (Sass/CSS compiling/minifying/linting, SVG Spriting)
* `yarn babel` (ES6 transpiling, javascript minification)
* `yarn storybook` (Storybook dev watch task)

If you don't care about Storybook, you can use `yarn watch` (combination of `yarn webpack` and `yarn babel`)

For only compiling assets, run `yarn build`.

### Storybook

You can compile a storybook with the command `yarn storybook`.

This combines 3 tasks:
* `yarn build-webpack` (same as `yarn webpack` but without watch task and using production webpack file)
* `yarn build-babel` (same as `yarn babel` but without watch task)

### Style guide

TODO

## Custom features of Tampere

### Hyphenation
Tampere comes packaged with [Hyphenopoly](https://github.com/mnater/Hyphenopoly) which is a hyphenation polyfill intended for browsers which do not support automatic hyphenation. You can enable the library and then use the default CSS class `.hyphenate`
to enable hyphenation.

### Translations
String in the theme are translated in /translations %language.po files. Create a new file to add another language, e.g. sv.fi for swedish translations.
To import trasnlations, run `drush locale-check && drush locale-update && drush cr`. Notice, that you need to have `locale` module enable.

### Language switcher dropdown
By default language links block is rendered as a list of links. In most cases we need it to be a dropdown element. There is template override for it in a templates/navigation/links--language-block.html.twig that takes care of converting list to an accessible dropdown.
in tampere.theme you will find a `tampere_preprocess_links__language_block` function that gets an active language code to display in a template. Also, there is `language-dropdown` library attached that add a functionality and some sytles to the dropdown.

## Frontend gotchas

### Local tools setup
When doing theme development, you should disable page and render caches in your local environment for avoiding constant `drush cr`s.
Also you need Twig debug to be configured.

### Using components in Drupal templates
When component is ready, you can use it in the twig templates under the `/templates` by including, extending or embedding them. Have a look how it's done in existing templates. Essentially, your task is to pass real drupal variables to the Pattern Lab components.

Don't forget to clear cache to make your new template visible!

### Using local custom fonts
Place all custom fonts to the root `/fonts` folder.

Webpack is used to load all the font files to your theme. You can then use the custom file by adding the required font face to the theme .scss file. For example:
```
@font-face {
  font-family: 'Custom-Font';
  src: url('../fonts/CustomFont/CustomFont.otf') format('opentype');
  font-style: normal;
  font-weight: normal;
}
```

Now the font family is available to use:
```
$font-custom: 'Custom-Font', Arial, helvetica, sans-serif;
```

### Always have watch task running
Tampere's `yarn develop` perform css and js linting, as well as accessibility checks on a fly. Please, maske sure you correct all linting error before commiting your work.

### Something's wrong?

#### Your new component doesn't end up on the Storybook page

  - rerun `yarn develop` for the theme to register your new component
  - check that a name of the new component is not already in use. Rename it if that's the case.
  - check for the twig file name. Underscore '_' at the beginning of the name tells Pattern lab not to display it. Files with `_` will be used only in Drupal templates.

### Documentation

[Emulsify Design System](https://docs.emulsify.info/)

## Tampere specific theme features

### UI Patterns and Display Suite
In the project, all nodes are displayed with the help of either [UI Patterns](https://ui-patterns.readthedocs.io/) or Display Suite layouts.
Display Suite layouts are usually enabled for view modes that are used in preprocess functions that render the fields elsewhere. For example, there are a lot of paragraph types (e.g. Person liftup) that allow users to select what fields to show from a referenced node of their choice. These content types will usually use Display Suite layouts to set the displayed fields correctly so that the preprocess function needs only to display the selected field using the correct view mode (see `admin/structure/types/manage/person/display/contact_information_liftup_view_mode` and `modules/custom/tre_preprocess/src/Plugin/Preprocess/PersonLiftup.php`).

UI Patterns are used for some paragraph types, but mainly they are in use for the content type full view modes (e.g. Blog article and Collection page in default view mode). More information about how to create patterns can be found in the [UI Patterns documentation](https://ui-patterns.readthedocs.io/en/8.x-1.x/content/patterns-definition.html#). Patterns can also have variations available, and they can be selected in the UI at the bottom of the view display page where it says Pattern settings. The pattern variation can be accessed in the template the pattern uses with the `variant` variable (see `components/03-organisms/site/topical-content/topical-content.twig` for example where the variant is used as a modifier for another component).
