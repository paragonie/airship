# Account Security

## My Account

Login to the Bridge, then click "My Account". Here, you can change your
passphrase, or update some of your account settings.

At the top of the page you will notice a field called `Public ID`. This is how
other Airship users refer to you (e.g. to invite you to collaborate for an
[Author](01-Crew.md) profile). Your username is only used for authentication
and cannot be changed here. However, you may change your display name at any
time.

### Account Recovery Settings

Unlike most web software, Airship allows you opt out of account recovery emails
(this is mostly desirable for users equipped with password managers). However,
many people want this convenience and are willing to sacrifice some security.

To enable account recovery, Airship requires two things:

1. A valid email address.
2. The "Allow Password Reset" box to be checked.

Optionally, if you use [GnuPG](https://www.gnupg.org), you may supply your GPG
public key in the text box below. We will use this public key to encrypt the
email containing your account recovery link.

## Enabling Two-Factor Authentication

Login to the Bridge, then click the "Two-Factor Auth." link in the left menu
under "My Account".

You should see a QR code and a brief form with two checkboxes.

Scan the QR code with your Two-Factor Authenticator device, then check the
"Enable Two-Factor Authentication?" checkbox and press Save Preferences.

The other checkbox should only be used if you believe someone else obtained
the contents of the QR code. If this happens, check that box then press Save
Preferences. Your QR code will then change and anyone attempting to use the old
code will not be granted access.
