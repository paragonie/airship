# Airship Lingo / Jargon

## Airship Technical Terms and Concepts

### Airship Extension Types

When [developing custom extensions for CMS Airship](../04-developer-docs),
there are three types of extensions supported by our architecture:

1. **Cabins**
   * Cabins are self-contained applications. Cabins can have their own Gadgets
     and Motifs developed specifically for them. Universal Gadgets and Motifs
     can also be applied.

     For example, if you wanted to build a shopping cart in Airship, you would
     most likely want to develop a Cabin rather than extend the functionality
     of one of the existing Cabins (Bridge and Hull).
2. **Gadgets**
   * Gadgets are intended to affect the *behavior* of an existing Cabin (or all
     Cabins). From within a Gadget, you can extend the functionality of core
     framework features (via the Gears API we provide), add new Landings to an
     existing Cabin, and much more.
3. **Motifs**
   * Motifs are intended to affect the *appearance* of an existing Cabin (or
     all Cabins). A Motif can override the base template or define new CSS and
     JavaScript files.

### Main Airship Directories

* `Alerts`  -> Exceptions
  * This defines the framework-specific Exceptions and Errors that can be
    thrown
* `Cabin`   -> Described above
  * Each Cabin comes with its own Blueprints, Landings, Lenses, Motifs, and
    optional Gadgets
* `Engine`  -> Core framework files
  * These classes power the entire airship; most classes can be upgraded
* `Gadgets` -> Described above
  * Adds features to an existing cabin (or to all cabins). They exist as a PHP
    Archive file (.phar) and associated Ed25519 signature.
* `Installer` -> (Self-explanatory)
  * Our installer code is self-contained and strictly outside of the
    document root.
* `Motifs`  -> Described above
  * Community-provided templates and stylesheets. Can be assigned to a specific
    Cabin or universal.
* `config`  -> Configuration
* `lang`    -> Language-specific stuff (for internationalization)
* `public`  -> Public web root (point your webserver here)

### Airship Architecture

Our architecture is *similar* to MVC. In addition to making the terminology 
thematically appropriate, we don't have View objects, we simply use templates
(rendered by Twig).

Additionally, adopting our own lingo allows us some flexibility in our design
decisions without offending the purists. **True MVC** doesn't make a whole lot
of sense in PHP applications anyway.

#### Blueprint ~~ Model

A Blueprint is analogous to a Model in a traditional MVC framework.
It should be responsible for handling database interactions.

#### Lens ~~ View

A Lens is analogous to a View in a traditional MVC framework.
Lenses are template files rendered by Twig.

#### Landing ~~ Controller

A Landing is analogous to a Controller in a traditional MVC framework. Landings
are your passengers' destinations. Landings are typically database-agnostic and
mostly deal with passing data to a Blueprint and then passing it to the
template.

## Airship Culture

### Crew

Collectively refers to Engineers, Pilots, and Passengers.

### Engineer

An engineer is someone who develops Motifs, Gadgets, or Cabins for their own
Airship or for others'.

### Pilot

A pilot is an administrative user. They may share their power and responsibility
with co-pilots, but this vessel flies by their rules.

### Passenger

An unprivileged user. They're along for the ride. Most blogs call them readers,
but that's rather unimaginative. Where's their sense of adventure?

[Next: Installing Airship](2-Installing.md)
