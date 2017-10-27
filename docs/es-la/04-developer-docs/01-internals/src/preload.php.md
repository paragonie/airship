# src/preload.php

This is loaded before everything else in the bootstrapping process.

It's automatically included as part of the bootstrap process.

## Constants

### `IDE_HACKS`

Type: `bool`

Value: `false`

Purpose: Used to assist code-completion with IDEs. The contents of a code
block wrapped in `if (IDE_HACKS)` should never be executed.

### `ROOT`

Type `string`

Purpose: The root directory of your Airship install, from the perspective of
Airship. It references the `src` directory.

### `AIRSHIP_UPLOADS`

Type `string`

Purpose: The directory that files will be actually uploaded to.

### `ISCLI`

Type: `bool`
Purpose: An answer to the question, "Are we running a CLI script?"
