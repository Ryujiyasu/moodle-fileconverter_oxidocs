# Oxi document converter for Moodle

A document converter (`fileconverter_oxidocs`) that turns Word, PowerPoint and Excel submissions into PDF **on your Moodle server**, so teachers can annotate them with *Annotate PDF*. It runs [oxidocs](https://github.com/Ryujiyasu/oxi), a single program that lays documents out the way Word, PowerPoint and Excel do. There is no LibreOffice to install, and no file is sent to Google or anyone else.

Why: Moodle's own converters are [unoconv](https://docs.moodle.org/en/unoconv), which Moodle documents as deprecated (its successor unoserver is not supported, MDL-75066), and Google Drive, which uploads every submission to Google. Without a converter, teachers can only annotate submissions that are already PDF.

## What it converts

| From | To |
|---|---|
| `.docx`, `.docm`, `.dotx`, `.dotm` | PDF |
| `.pptx`, `.pptm`, `.ppsx`, `.ppsm`, `.potx`, `.potm` (oxidocs builds from `oxidocs-v0.9.0-static.4` on) | PDF, one page per slide |
| `.xlsx`, `.xlsm`, `.xltx`, `.xltm` (oxidocs builds from `oxidocs-v0.9.0-static.5` on) | PDF, the sheets' print pages |

The plugin asks the oxidocs it is pointed at which of these it converts, so updating oxidocs is enough to add a format. Everything else (`.doc`, `.odt`, …) is left to your other converters.

**Hand-over instead of a broken PDF.** With *Hand over what Oxi cannot fully draw* on (the default), a document that holds content oxidocs does not draw yet (some chart types, slide gradients, for example) is reported as failed rather than converted with that content missing, and Moodle tries the next enabled converter. Put Oxi first in the list and keep another converter below it if you have one.

## Install

1. **oxidocs.** Download the static Linux build (it needs nothing else on the server) and put it on the path the plugin expects:

   ```sh
   curl -LO https://github.com/Ryujiyasu/oxi/releases/download/oxidocs-v0.9.0-static.5/oxidocs-x86_64-unknown-linux-musl.tar.gz
   echo "6c9cbdb012f1b577e6d98a74f1475305a468e210b19c311a38d5c6316c7eb967  oxidocs-x86_64-unknown-linux-musl.tar.gz" | sha256sum -c -
   tar -xzf oxidocs-x86_64-unknown-linux-musl.tar.gz
   sudo install -m 755 oxidocs-x86_64-unknown-linux-musl/oxidocs /usr/local/bin/oxidocs
   ```

   For ARM servers use `oxidocs-aarch64-unknown-linux-musl.tar.gz` (SHA-256 `cc5e9cdaccadd490baab3dd4a522c6ba3d06ff256535af190384ed32ebe49c4c`). `pip install oxidocs` also installs the program.

   oxidocs carries the fonts it needs for Latin and Japanese text. For other characters (Greek letters such as π, for example) it uses the fonts installed on the server, so on a server with no fonts at all install a general one, such as `fonts-dejavu-core` on Debian and Ubuntu.

2. **The plugin.** Copy this repository to `files/converter/oxidocs` in your Moodle code (`public/files/converter/oxidocs` on Moodle 5.1 and later), then visit *Site administration > Notifications*.

3. **Enable it.** *Site administration > Plugins > Document converters > Manage document converters*: enable **Oxi** and move it to the top.

4. **Check it.** On the plugin's settings page, *Test the oxidocs path* runs the program and offers a converted test document.

## Settings

| Setting | Default | |
|---|---|---|
| Path to oxidocs | `/usr/local/bin/oxidocs` | The program the plugin runs. |
| Hand over what Oxi cannot fully draw | on | See above. |
| Time limit | 60 seconds | A conversion that takes longer is stopped and handed on. |

## Privacy and licence

Files are converted in a temporary folder on the Moodle server and the PDF is stored in Moodle's file storage. Nothing is sent over the network, and the plugin stores no personal data.

This plugin is GPL-3.0-or-later, like Moodle. oxidocs is a separate program, MPL-2.0, downloaded from its own releases.

## Tested

Moodle 4.5.16 with PHP 8.3 and PostgreSQL 16, oxidocs `0.9.0-static.5`: a Word document, a three-slide deck and a two-sheet workbook converted through Moodle's own conversion API (`\core_files\converter`), and a deck with a gradient handed over under the strict setting. A Word submission converted and opened in the assignment grader for annotation. CI runs Moodle's plugin checks and the PHPUnit tests on Moodle 4.1, 4.5, 5.0, 5.1 and 5.3.

Developed with AI assistance (Claude); reviewed by the maintainer.
