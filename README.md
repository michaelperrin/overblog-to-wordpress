OverBlog to WordPress
=====================

## Run a local instance of WordPress

Copy `.env` file to `.env.dist`.

Run:

    make wordpress_start

Access website on http://localhost:8100

## Configure WordPress for the import

* Install the *miniOrange WordPress REST API Authentication* plugin and activate Basic Authentication.
* Allow users to add a comment with no username or email address.

## Run import

With PHP WordPress functions:

    docker-compose run --rm php ./app.php wp:import-overblog data/export3-fix.xml

With API:

    ./app.php wp:import-overblog-with-api <file> <wordpress_base_uri> <username> <password>

Options:

* `--ignore-images`: do not import images
* `--limit=123`: Max number of posts to import

## Find errors in XML file

    xmllint --stream data/file.xml
