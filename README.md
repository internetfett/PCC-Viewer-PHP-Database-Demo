# PCC Viewer PHP Database Demo

This repository demonstrates a sample of storing individual viewer annotations in a database.

## Strategy

Facilitated with SQLite, we have created a straightforward example of how to use the new ViewerControl API calls (serializeMarks and deserializeMarks) to prepare annotation marks for manipulation by a developed web tier. This example isn’t meant to be a deployable sample. Instead, it is a demonstration of approach and makes many assumptions, such as database design, when performing its demonstration. Further, SQLite is not an optimal choice for a deployable solution. Other DBMS’ will offer native support for JSON structures that preclude the need for complex database architecture and complex queries based on that architecture. PostgreSQL and Oracle are two RDBMS’ that have native support for representing JSON structures. Other solutions, like MongoDB a NoSQL database, entirely represent their database objects as JSON structures.

In this example, the database was designed with a couple of assumptions:
   - that there is some document information stored in the documents table
      - for this example, the documents table is populated when annotations are stored
   - all annotation “files” must be associated with one, and only one, document
   - all marks (annotations) must be associated with one, and only one, annotation “file”
   - each annotation may/may not have one or many comments

For greater clarity, please see the attached image. A user entity is also shown in the image to demonstrate ownership of annotation creation. In this structure, a user creates an annotation file that contains zero or many annotation or marks. From basic designs like this one, we can begin answering questions like:
   - How many comments has “user A” left in the past two weeks?
   - Which annotations have comments that contain the word “approval”?
   - Which user has averaged the highest number of annotations per page for the past month?
   - Which types of documents have the highest average of marks per page?

Each solution will need to answer its own questions regarding how the data generated from those methods, specifically serializeMarks, should be handled. In this sample, all marks are stored without attribution to a specific user. In a deployment, or even a proof of concept, that would most likely not be the case. For our purposes, many of those questions were passed over in favor of demonstrating the general approach.

## Setup

This demo requires that SQLite and the PHP PDO driver for SQLite is installed as well as the other base requirements for the Prizm Content Connect PHP viewer sample (see: http://goo.gl/PiFfgR).

### To install SQLite:

On Linux:
   - `sudo apt-get install sqlite3`
   - `sudo yum install sqlite3`
   - depending on the location of the web application, you may need to grant the web user read-write access to the directory, in order to allow SQLite to create its database

On Windows:
   - Visit http://www.sqlite.org/download.html
   - Download **sqlite-shell-win32-*.zip** and **sqlite-dll-win32-*.zip**
   - Unpack both archives to a directory in your PATH environment variable, such as `C:\WINDOWS\system32`, or to a new directory. If you unpacked to a new directory, you will have to add that directory to your system PATH.
   - Open a new command prompt and issue “sqlite3” command to verify successful installation.

### To install the PDO SQLite driver (this may not be required depending on your distribution):

On Linux:
   - `sudo apt-get install php5-sqlite`
   - `sudo yum install php5-sqlite`
   - Apache restart may be required

On Windows:
   - Drivers should be enabled by default

### pcc.config

The folder `/full-viewer-sample/viewer-webtier` contains two versions of the config file, named `pcc.win.config` and `pcc.nix.config`. Please rename the appropriate file to `pcc.config`, depending on the environment on which you are deploying the sample.

## Use

Selecting the Annotations tab will show that there are two duplicated buttons on the top-right side of the viewer. Tools tips for each of these buttons are different. "Post to DB" will collect the current marks on the page and post them back to the server for database insertion. "Load from DB" will select an arbitrary set of annotations from the database and load them into the current viewer. Which set of annotations are loaded can be changed by referring to the hardcoded parameter in the anonymous function associated with load method in "index.php".

## Changes from default sample

There are a few minor changes to enable this database insertion/loading sample.

### UI Changes

In `full-viewer-sample/viewer-assets/templates/viewerTemplate.html`, the following lines were added to the "annotate" and "redact" tabs:

    <button class="pcc-icon pcc-icon-save pcc-js-postToDB" title="Post to DB"></button>
    <button class="pcc-icon pcc-icon-load pcc-js-loadFromDB" title="Load from DB"></button>

This addition adds two new buttons to the annotations tab. We’ll use these to trigger database interaction.

### Main Page `index.php` Changes

Two new anonymous jQuery functions were added and bound to the buttons above. You can locate the functions by searching in `full-viewer-sample/index.php` for `postToDB` and `loadFromDB`.

### New file `dbDemo.php`

Located in `full-viewer-sample/viewer-webtier`, this page is handles the requests from the client and the database.
