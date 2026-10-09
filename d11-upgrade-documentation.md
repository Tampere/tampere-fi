# Requirements

- PHP version 8.3 minimum,
- Composer version 2.7
- Drush 13
- Production server using database version 5.0 compared to dev servers which is 8.0

# Before D11 production(also dev servers) deployment:

- Remove incompatible modules from core.extension.yml file and make a deployment first, so that the changes in composer from D11 branch will uninstall the modules.

# Documentation

- Removed jsonapi_boost inactive module, as it was not compatible
- Uninstalled cache_tags module, as it was not compatible
- form_display_field_machine_name Drupal 11 compatibility patch could not be applied due to some format issue, so instead applied own patch. Need to check if later the patch issue is resolved.
- form_display_field_machine_name, and updated to latest
- Quick node clone was using dev fork and was used as excluded repository so remove it and updated the module to the latest so that it is compatible with core.
- Webform module updated from version (6.2.9) as it does not support Drupal 11. To move forward with the D11 upgrade, updated to the newer 6.3.0-rc2
- Down scale components module as the 3.2 version contains a bug that will break drupal core 11.

# DB updates required by

- group content menu
- Quick node clone

# In the server

- updated composer in dev to 2.7
- Update drush, minimum version is 13
  -- Error after updating to drush 13, logger error on running drush commands
  --- https://www.drupal.org/project/do_username/issues/3472367

# To check

- Check if the patch for markdown module needs to be added:
  -- // "drupal/markdown": {
  // "3283349 - Add support for Commonmark v2": "https://www.drupal.org/files/issues/2026-02-10/markdown-3283349-46.patch"
  // },
  -- Updating this cache cause issues so removed it, need to check if the functionality is affected

- Check if this custom patch needs to be added with improvements, currently it does not get applied.
  "drupal/cookieinformation": {
  "Add support for readspeaker and Askem to cookie information": "patches/add_support_for_readspeaker_and_askem_to_cookie_information.patch"
  },

- Permission watch dog module. This patch, cannot be applied and there is no new version:
  -- Cannot apply patch 3340219 - Log permission changes using Drupal logger as well (https://www.drupal.org/files/issues/2023-02-09/log-using-drupal-logger-in-permission-watchdog-3340219.diff)!
  -- https://www.drupal.org/project/permission_watchdog/issues/3340219

- Check in staging, how the overides option work for feeds or whatever that I don'¨t know yet
  -- Migrate Override module update

# In the next iteration

- Remove cache tags module from composer, currently only removed from drush, drupal.
- Remove path_guard module

# To client

- Inform the client, that the path guard module has been installed and path validation won't work any more.

# Uninstalled but needs to be removed from composer

- System status
- Cache tags
- Path Guard
- Variations cache
  -- Included in core and current version is obsolete after core 10.2 version [variationcache](https://www.drupal.org/project/variationcache)
  -- Checked the customs module section and found no traces of code Drupal\variationcache namespace

# Remove older modules with better alternatives or update to newer versions

- Update telephone_plus to version 2.x
  -- https://www.drupal.org/project/telephone_plus/issues/3434933
- SCN module is pout of sync, no updates and loose patch was applied for D11 compatibility.
  -- https://www.drupal.org/project/scn
  -- After the Drupal updates suggest the client to remove the module and add Advanced Entity Notify module instead
  -- https://www.drupal.org/project/entity_notify
  -- https://www.drupal.org/project/scn/issues/3472113
  -- Does same job but for update to date.
  -- Advanced module added and SCN module removed
- theme_breakpoints_js, updated to version 2.x that is compatible with php version 8.3
  -- https://www.drupal.org/project/theme_breakpoints_js

# Overidden repositories/ modules:

Old versions of the modules have been added by tricking composer and then tweaked so that it is compatible with D11. Some have working patches, but others custom changes.
- "drupal/form_display_field_machine_name",
- "drupal/migrate_override",
- "drupal/webform_paragraphs"
- "drupal/group_scheduled_transitions"
- "drupal/form_display_field_machine_name"

# Test custom module, in servers

- Tre contact search
- Email release
- Ptv import
- Tre current content archive search content module
- Tre jsonapi_custom
- Tre preprocess embedded content and map tabs

# After D11 patches not working and no working solution:

- "2968207 - Allow multiple instances of the same exposed filter form on a single page": "https://www.drupal.org/files/issues/2023-11-06/2968207-44.patch"
- "2511878 - Support enclosure field in Views RssFields row plugin - D10.3": "https://www.drupal.org/files/issues/2026-04-16/drupal-core-2511878-94.patch"
- 3544159: Missing translations for relevancy levels": "https://git.drupalcode.org/issue/ai_auto_reference-3544159/-/commit/8737c674b3b23e1d51e3433da1d1c93b0cda5abf.patch
- 3490601: Add feature to select languages this feature is enabled on (https://git.drupalcode.org/issue/ai_translate_textfield-3490601/-/commit/7bc89a0a91291805646de0e23414d7477bed53fe.patch)
  -- Seems to be fixed in core
- No fix, "3503517: Support for other field types like Link or Table fields": "https://www.drupal.org/files/issues/2025-02-04/Support-for-other-field-types_3503517_3.patch",
- No fix either, "3582430: Pass explicit source language to AI Provider to support glossaries": "https://www.drupal.org/files/issues/2026-03-31/
  "drupal/scheduled_transitions": {
  "XO-3817 - Better translation revision support for scheduling": "https://www.drupal.org/files/issues/2025-10-15/3083616-12.patch-2.7.x.patch"
  },ai_translate_textfield-source-lang.patch"
  "drupal/memcache": {
  "#2996615: Transaction support for cache (tags) invalidation": "https://www.drupal.org/files/issues/2026-06-04/transaction_support-2996615-101.patch"
  },
- Need to apply custom patch, again with new code:
  "drupal/components": {
  "3107993 - Template is not defined error on admin pages when using administration theme": "patches/components**template_is_not_defined_error_on_admin_pages_when_using_administration_theme**3107993.patch"
  "drupal/jquery_ui": {
  "3339013 - implement datepicker localisation in jquery_ui 2.x": "patches/implement_datepicker_localisation_3339013.patch"
  },
  },

# Deploying to production
- First deploy SCN module uninstall in separate deployment. So it requires to deployment. 
- Second deployment will fail when trying to clear cache before drupal updates.
- So, run a thrid deployment after, manually updating database.
- then manually import configs.
