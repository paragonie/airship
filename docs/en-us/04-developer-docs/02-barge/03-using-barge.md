# Using Barge

After you've installed `barge` and created a Supplier account, you need to login
to use it effectively.

Run this command:

```sh
barge login
```

You will be prompted for your username and passphrase.

## Your First Run - Generating your First Keys

All CMS Airship extensions must be cryptographically signed by their supplier.
Thus, the first thing you should do once logged in is create your first two 
keys.

Run this command:

```sh
barge key generate
```

You will be asked if you want to store a part of your key (called the "salt")
in the Skyport? We recommend "No", but if you prefer convenience, this will
allow us to maintain a partial backup of your key (that can only be used with
your password-- which we will never know).

The first time you run this command, you will generate a **master** key. There
are two kinds of keys that can be created:

* **Signing keys** are used to sign your extensions.
* **Master keys** are used to create/revoke new keys.

You should take extra precautions with handling your master key. Anyone who
obtains a copy of it will be able to revoke your keys and replace them with
their own.

Once you have your master key, the next step is to generate your first signing
key. Run the above command again:

```sh
barge key generate
```

This time, you will be prompted for which type of key you wish to generate. You
can just type `s` for signing keys.

Additionally, after you enter your passphrase for the new signing key, you will
be prompted for the passphrase for your existing master key. This is because 
new signing keys must be signed by an existing master key.

The only time a key can be generated or revoked without being signed by a master
key is when you create your very first one for your account.

At this point, you should have:

* A supplier account
* A copy of `barge` installed
* One master key
* One signing key

Now you are ready to begin CMS Airship extension development.