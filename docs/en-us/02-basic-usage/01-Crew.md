# Crew

This section covers the basics of user accounts, Authors, and collaboration.

## Users

Each user is identified by a username and a password. The password is
never stored directly; instead, an Argon2i password hash is calculated
from the user's password, then this hashed value is encrypted. The
final ciphertext is stored in the database.

* A user account is just for authentication and access controls.
* Anything you publish gets attributed to Authors, not Users.

## Authors

Authors are shared pen-names. Each user may have access to multiple
authors, and each author may be shared across multiple users.

* An author is a public persona, decoupled from your user account, that
  you can (optionally) share with other users.
* To invite users to collaborate under an Author identity, you only need
  the user's Public ID, which is a distinct randomly generated value from
  their username and display name.

### Creating Your First Author

After logging into the Bridge, click the Authors link in the left menu. Click
the "Create a New Author Profile" button.

* **Author Name** - A pen name or the department.
* **Byline** - This can be a title (e.g. *Freelance Copywriter*) or a quote.
* **Biography** - Describe this author profile. What is your purpose in writing
  blog posts?

### Collaborating with Other Users

After logging into the Bridge, click the Authors link in the left menu. Each
author will have four buttons. If you hover your mouse over them, you will see
a tooltip that explains what each button does.

Click the second icon (mouse hover tooltip: "Manage Members").

![Screenshot: Which icon to click on](bridge_author_users.png)

If you wish to invite a user to collaborate, you first need their Public ID.
Users can find it on the Bridge homepage or in My Account.

![Screenshot: How to quickly locate your Public ID on the Bridge homepage](bridge_home_user_public_id.png)

![Screenshot: How to quickly locate your Public ID in the My Account page](bridge_my_account_user_public_id.png)

## Groups

Groups are just collections of users, with a twist: Groups can belong to
other groups. This recursive nature is useful for assigning permissions
to e.g. an entire department.

* A user may belong in multiple groups.

Airship provides this group arrangement by default:

* Guest
* Registered User
  * Moderator
    * Administrator
  * Publisher
  * Trusted Commentor
  * Writer

An Administrator is also simultaneously a Moderator and a Registered User.

To learn more about permissions, see [the Permissions section in the End Users' Guide](../03-end-users-guide/Bridge/Permissions.md).

[Next: Using the Blog Features](02-Blog.md)
