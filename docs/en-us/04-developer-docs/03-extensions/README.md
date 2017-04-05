## The General Extension Development workflow

[After you have `barge` installed, are logged into a Skyport account, and have generated your keys](../02-barge),
you can begin to develop your own Airship extensions. 

The general workflow goes like this:

1. Initialize your new extension.
2. Write your code.
3. Build the extension.
4. Sign the extension.
5. Release the extension.

### Creating a Motif

```sh
barge motif
# ...Then follow the prompts

# Begin making your code changes:
cd your-new-project-name
```

### Creating a Gadget

```sh
barge gadget
# ...Then follow the prompts

# Begin making your code changes:
cd your-new-project-name
```

### Creating a Cabin

```sh
barge gadget
# ...Then follow the prompts

# Begin making your code changes:
cd your-new-project-name
```

### Publishing Your Changes

```sh
# Build a .phar (Gadgets and Cabins) or .zip (Motifs):
barge build

# After building, feel free to manually install the deliverable in a local
# Airship installation.

# Once you are satisfied with the quality of your extension, sign it:
barge sign

# To make your extension available on Skyport:
barge release
```

## In-Depth Guide

  * [Developing Motifs - Change the way your Airship looks](01-motifs)
  * [Developing Gadgets - Alter an existing Airship Cabin](02-gadgets)
  * [Developing Cabins - Create your own Airship applications](03-cabins)
