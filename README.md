# xom_web_services

## Introduction
Provides various web services for XIV on Mac and IINACT, including updating and checking of various FFXIV endpoints.

This module can be used standalone; however, it is recommended to be placed behind a caching proxy for best results.

## Requirements

This module currently relies on the following:

* Drupal 9
* PHP 8.x or higher

## Security

This module relies on Drupal's permissions and authentication middleware and thus does not provide any access checking on its own.

In addition to the above, Drupal itself handles brute-force and CSRF protection.

Please visit https://www.xivmac.com/.well-known/security.txt for up to date contact information to disclose security vulnerabilities.