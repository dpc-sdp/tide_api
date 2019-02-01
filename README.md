# Tide API
Content API functionality of [Tide](https://github.com/dpc-sdp/tide) distribution

[![CircleCI](https://circleci.com/gh/dpc-sdp/tide_api.svg?style=svg)](https://circleci.com/gh/dpc-sdp/tide_api)

## Tide
Tide is a Drupal 8 distribution focused on delivering an API first, headless 
Drupal content administration site.

# CONTENTS OF THIS FILE

* Introduction
* Requirements
* Recommended Modules
* Installation

# INTRODUCTION
The Tide API module provides the content API functionality and related 
configurations. This module is required in case you want to use your site in a 
headless manner.

# REQUIREMENTS
* [Tide Core](https://github.com/dpc-sdp/tide_core)
* [JSON:API](https://drupal.org/project/jsonapi)
* [JSON:API Extras](https://drupal.org/project/jsonapi_extras)
* [Open API](https://drupal.org/project/openapi)
* [Schemata](https://drupal.org/project/schemata)

# INSTALLATION
Include the Tide API module in your composer.json file
```bash
composer require dpc-sdp/tide_api
```

# Caveats

Tide API is on the alpha release, use with caution. APIs are likely to change 
before the stable version, that there will be breaking changes and that we're 
not supporting it for external production sites at the moment.
