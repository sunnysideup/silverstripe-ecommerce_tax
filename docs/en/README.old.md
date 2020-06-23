
ecommerce tax
================================================================================

GST Tax
================

What is it for?

Allows adding GST / VAT / any aother tax to an order

The concepts you need to know are:

* when the prices are inclusive, the site needs to have a default country, so that you know for which country the tax is added.
* in general, prices apply to all products, but using the GSTTaxDecorator you can make buyable (product) specific exceptions.
* you can add zero or more taxes through modeladmin.
* the taxes can either apply to one country or to all countries. In the future we may change this so they they can apply to more than one country (e.g. EU) or to specific regions (e.g. US state specific tax).
* you can either choose for the tax to apply to all buyables, or you can make it an "optional tax".
* when you have added the GSTTaxDecorator to the buyables you have two additional options: optional taxes can be added on a per-buyable basis and taxes that apply to all buyables can be removed from individual buyables.
* taxes also apply to all modifier charges except any GSTTaxModifiers.
* for both modifiers and buyables you can change the taxable amount by adding a method "portionWithoutTax" (this name of this method in is configurable)




Developer
-----------------------------------------------
Nicolaas [at] sunnysideup.co.nz


Documentation
-----------------------------------------------
Please contact author for more details.

Any bug reports and/or feature requests will be
looked at

We are also very happy to provide personalised support
for this module in exchange for a small donation.


Requirements
-----------------------------------------------
see composer.json


Project Home
-----------------------------------------------
See http://code.google.com/p/silverstripe-ecommerce

Demo
-----------------------------------------------
See http://www.silverstripe-ecommerce.com


Installation Instructions
-----------------------------------------------

1. Find out how to add modules to SS and add module as per usual.

2. Review configs and add entries to app/_config/config.yml
(or similar) as necessary.
In the _config/ folder of this module
you can usually find some examples of config options (if any).





