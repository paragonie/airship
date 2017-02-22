# Cargo Refresher

Just in case somebody is wondering why there are so many files in this
directory: The purpose of the cargo system is to allow plugins to
override how certain UIs are implemented. By default, it looks in
CurrentCabin/Lens/cargo/{name here}.twig.

You shouldn't be changing the files directly; instead, define a new one
and register it with:

    \Airship\Engine\Gadgets::loadCargo(
        'cargo_identifier',
        'path/to/template.twig'
    );

