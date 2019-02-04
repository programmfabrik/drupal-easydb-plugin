INTRODUCTION
============

This module connects to the
[easydb digital asset management system](https://www.programmfabrik.de/easydb/):
You can select images in easydb and copy them to your Drupal.


REQUIREMENTS
============

The module requires:

* Drupal 8.4.x or higher (for the Media module)
* [Entity Browser](https://www.drupal.org/project/entity_browser)


RECOMMENDED MODULES
===================

* [ctools](https://www.drupal.org/project/ctools) if you want to edit the entity
  browsers using a graphic user interface (see the "Configure the Entity
  Browser" section below)
* Content Translation (in Drupal core) if you want to import multilingual
  metadata to a multilingual site


INSTALLATION
============

Download and enable the
[Entity Browser](https://www.drupal.org/project/entity_browser) and easydb
modules as usual, e.g.:

* place them in a subdirectory under the modules directory and
* enable "easydb File Picker" under *Extend* (`/admin/modules`).

The Media module (Drupal core) is also required; so you will be asked if you
want to install Media, too, when installing the easydb module.


CONFIGURATION
=============

Go to *Configuration* > *Media* > *easydb* (`/admin/config/media/easydb`) and
enter the domain of your easydb server and set up the language mapping.

Go to *People* > *Permissions* (`/admin/people/permissions#module-easydb`) to
set up the permissions. (Each role that should be able to copy images from
easydb needs the "Use easydb File Picker" permission. The "Administer easydb
File Picker" permission could be given to the same roles like "Administer site
configuration" under *System*.)


SITE BUILDING
=============

TL;DR: Try the easydb Example module.

There are several options to use an easydb image field in your Drupal:

* use the easydb Example module,
* use the `field_easydb_image` field and add it to your content type, or
* create your own field.


The Example Module
------------------

You can optionally enable the easydb Example module, too. This will create a
demo content type ("Article with easydb Image") similar to the standard article
content type but with an easydb image field instead of the usual simple image
field. This module requires some fields from the standard article content type –
which are available if you used the "Standard" profile when installing Drupal.

You can then adapt this demo content type to your needs by changing the name or
adding/removing fields, or just use it as an example for adding an easydb image
field to your own content type.


The field_easydb_image Field
----------------------------

To add the `field_easydb_image` field in your preferred content type,

* go to *Administration* > *Structure* > *Content types*
  (`/admin/structure/types`),
* choose your content type where you want to add an "easydb image" field and
  click *Manage fields*,
* *Add field* > Select "Entity reference: field_easydb_image" from *Re-use an
  existing field* > then *Save and continue*.
* On the next page under *Reference type*: choose at least "easydb image" from
  *Bundles* and optionally *sort*, for example, by "Changed" ("Descending")


Create Your Own Field
---------------------

Create a field in any content type. The *field type* must be "Entity reference".
Here is an example for the other settings:

* Under *Manage form display*, use the "Entity browser" widget. You can use the
  "easydb browser" from the module here or adapt it or create your own entity
  browser as described below.
* Under *Manage display*, you may use the "Rendered entity" format and select an
  existing "View mode" (or create a custom one before under *Structure* > *Media
  types* > (your media type) > *Manage display*, e.g.
  `/admin/structure/media/manage/easydb_image/display`, and possibly *Structure*
  > *Display modes* > *View modes*, `/admin/structure/display-modes/view`).


Configure the Entity Browser
----------------------------

You can edit the "easydb browser" or create a new one under *Configuration* >
*Content authoring* > *Entity browsers* (`/admin/config/content/entity_browser`)
(this graphic user interface requires the [ctools
module](https://www.drupal.org/project/ctools)) or by editing the configuration
yaml file.

You can change the displayed tabs and some settings like automatic selection
(for less button clicks when selecting the images), which tabs are available, or
a dropdown menu instead of tabs.

There are two points where an automatic selection (and thus less "Select files"
and "Use selected" clicking) can be turned on. The following describes them
within the graphic user interface mentioned above:

* *General information* > *Selection display plugin*: choose "No selection
  display"
* *Widgets* > *Automatically submit selection* (for views)


Configure the File Previews
---------------------------

You may adjust the "Image with caption" (image_with_caption) view mode to your
needs, under *Structure* > *Media types* > *easydb image* > *Manage display* >
*Image with caption*
(`/admin/structure/media/manage/easydb_image/display/image_with_caption`).

The view used in the "Files Listing" tab in the entity browser can be adapted
like every other view under *Structure* > *Views* > *easydb Media Listing* >
*edit* (`/admin/structure/views/view/easydb_entity_browser`).

The image preview after fetching from easydb is the "Thumbnail" view mode of the
"easydb Image" media type, see *Structure* > *Media types* > *easydb Image* >
*Manage display* > *Thumbnail*
(`/admin/structure/media/manage/easydb_image/display/thumbnail`).


CONTENT EDITING
===============

In the default setup, there will be two tabs in the image selection field
(entity browser):

* *Copy from easydb*: Click "Fetch from easydb" to open a popup window, choose
  some image(s) there, and then click the *Drupal* button (top right) there.
* *Files listing*: lets you choose from the already copied images.

After choosing some image(s), click "Select files" and then "Use selected".

The default easydb entity browser is configured for "Multi step selection".
That's why you will see files in three stages, one below the other (which may be
the same sets of files): first the available files, then the currently picked
files, and finally the files in your content field. For the same reason, you
have to click "Select files" and "Use selected" to move the files from one stage
to the next one. This behaviour can be changed by configuring the entity
browser in a different way (see the "Configure the Entity Browser" section
above).


MULTILINGUAL SETUP
==================

If your Drupal site is set up as a multilingual site, the easydb File Picker
Module can automatically create translated media entities.

Make sure that:

* the Content Translation module is installed,
* you have chosen at least two languages on *Configuration* > *Regional and
  language* > *Languages* (`/admin/config/regional/language`),
* you have set up the language mapping on *Configuration* > *Media* > *easydb*
  (`/admin/config/media/easydb`),
* on *Configuration* > *Regional and language* > *Content language and
  translation* (`/admin/config/regional/content-language`), "Media" under
  *Custom language settings* is checked and then the media type "easydb image"
  is marked as translatable. Please
    - select at least "Caption", "Copyright text", "Description", "Keywords",
      "Title", "Alt" and "Title" under "Thumbnail", "Alt" and "Title" under
      "Image",
    - uncheck "easydb UID", "File" under "Thumbnail", "File" under "Image", and
    - take your own choice at all other fields.

If you want to translate the user interface to German for example (having the
Interface Translation module installed), go to *Configuration* > *Regional and
language* > *User interface translation* > *Import*
(`/admin/config/regional/translate/import`) and choose "de.po" as the
*Translation file*.


UNINSTALLATION NOTES
====================

When you uninstall the easydb File Picker module, the view "easydb Media
Listing" (code name `easydb_entity_browser`), the entity browser "easydb
Browser", and the field `field_easydb_image` will be removed. But the media type
"easydb Image" will remain. In case you want to install the easydb File Picker
module again after uninstalling it before, you will have to delete the media
type "easydb Image" first – go to *Structure* > *Media types* and choose
"Delete" for *easydb Image*
(`/admin/structure/media/manage/easydb_image/delete`).

Uninstalling the easydb Example module will not remove its demo content type
("Article with easydb Image", code name `easydb_article`). So, you could
uninstall the easydb Example module immediately after installing it. But you
can't re-install it again after having uninstalled it until you delete the
"Article with easydb Image" (`easydb_article`) content type manually.
