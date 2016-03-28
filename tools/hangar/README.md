# Hangar

A command line utility for assembling an Airship core update.

### Workflow

1. `hangar start` in the Airship directory (`src`).
2. `hangar add [file] [file2] ...` to add files to the patch, which will 
   overwrite core files when the end user updates.
3. `hangar autorun [file] [file2] ...` to add script snippets that will be run
   after overwriting the core files.
4. `hangar assemble` to compile the executable PHP Archive (Phar) with the
   update pack.
5. `hangar sign [pharfile]` is meant to be used in an air-gapped environment.
   This command will produce an Ed25519 signature of the BLAKE2b-512 hash of
   the PHP Archive for publishing.

