# Airship Lingo / Jargon

## Airship Culture

### Crew

Collectively refers to Engineers and Pilots.

### Engineer

An engineer is someone who develops Gears, Gadgets, or Cabins for their own
Airship or for others'.

### Pilot

A pilot is an administrative user. They may share their power and responsibility
with co-pilots, but this vessel flies by their rules.

### Passenger

An unprivileged user. They're along for the ride. Most blogs call them readers,
but that's rather unimaginative. Where's their sense of adventure?

## Airship Technical Terms and Concepts

### Main Airship Directories

* `Alerts`  -> Exceptions
  * This defines the framework-specific Exceptions and Errors that can be thrown
* `Cabin`   -> Apps
  * Each app comes with its own Blueprints, Landings, Lenses, and optional Gadgets
* `Engine`  -> Framework
  * These classes power the entire airship; most classes can be upgraded
* `Gadgets` -> Plugins
  * Adds features to an existing cabin
* `Gears`   -> Framework Alterations
  * Upgradeable Engine components - extends the core framework -
    **recommended for advanced users only**!
* `Installer` -> (Self-explanatory)
  * Our installer code is self-contained and strictly outside of the
    document root.
* `MotiFs`  -> Themes
  * Community-provided templates and stylesheets 
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

A Landing is analogous to a Controller in a traditional MVC framework.
Landings are your passengers' destinations. 

