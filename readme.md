# Private Media

Make files in the WordPress media library private. These are only accessible to logged in users.

Forked from [mattheu/Private-Media](https://github.com/mattheu/Private-Media). Thanks to [mattheu](https://github.com/mattheu) for doing all the hard work on this to begin with.

## Description

Private files are moved to a obsfucated location. Access can be completely restricted with .htaccess/nginx. URLs to private attachments are rewritten and the true location is not visible. Requests to these files hit a php script which authenticates the request and returns the file.

Non-private files are not affected.

Private attachments are not visible by default in the media library. Options are provided to filter results by private or public.

Access to Private files attachment pages in the front end is restricted. Will return 404 for logged out users.

## Installation

* Recursively git checkout `git checkout --recursive git@github.com:mikeselander/Private-Media.git`
* Install the plugin
* Edit an attachment, and check the private files option to set an attachment as private.
