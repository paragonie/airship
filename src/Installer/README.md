# The Airship Installer

This is just a web-based GUI for configuring the first-run of Airship. Thus, it
doesn't depend much on the Airship framework code.

All it needs to do is define some configuration, create the first user accounts,
and get people out the door as quickly as possible. This code is not meant to be
modular, flexible, or high-performance (but, of course, security does matter).

After installing, it is fully expected that you should be able to delete the
Installer directory entirely without negative consequence. From then on, your
Airship should receive over-the-air automatic security updates independent of
this component.

## Goals

* Security
* User experience (especially first-run experience)
* Self-Contained

## Non-Goals

* Super Optimized
* DRY
* Modularity
* Extensibility
* Reusability
* Integration with the Framework at Large
