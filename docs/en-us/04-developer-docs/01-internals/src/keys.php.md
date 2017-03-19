# src/keys.php

This sets up the contents of `$state->keyring`, based on the requirements set
in `config/keyring.json`. If the keys do not exist, they will be randomly
generated. (Exception: Public Keys do not make sense to generate on the fly.)

It's automatically included as part of the bootstrap process.