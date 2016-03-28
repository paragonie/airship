# Crew

This section covers user accounts, authentication, and access controls.

## Users

Each user is identified by a username and a password. The password is
never stored directly; instead, an Argon2i password hash is calculated
from the user's password, then this hashed value is encrypted. The
final ciphertext is stored in the database.

* A user account is just for authentication and access controls.

## Authors

Authors are shared pen-names. Each user may have access to multiple
authors, and each author may be shared across multiple users.

* An author is a public persona, decoupled from your user account, that
  you can (optionally) share with other users.

## Groups

Groups are just collections of users, with a twist: Groups can belong to
other groups. This recursive nature is useful for assigning permissions
to e.g. an entire department.

* A user may belong in multiple groups.

## Permissions

Airship uses a whitelist access controls system based on three concepts:

1. **Contexts**: Where are you in the application?
2. **Actions**: What are you trying to do?
3. **Rules**: Which users/groups are allowed to perform which actions in
   which contexts?

A particular permissions request can match many contexts, especially if 
there are overlapping patterns. When this happens, every context is 
validated and the permission request is only granted if they all
succeed. If there are no contexts matching a particular request, the 
request is refused (unless the user is an admin).

Each Cabin has its own set of possible actions (e.g. 'create', 'read', 
'update', and 'delete').

Rules grant a particular user or group the ability to perform a 
particular action within a particular context. Rules can only be used to 
allow access, not deny access. (That's what white-list means.)

If you set a rule to allow a group to perform an action within a given 
context, then all of that group's descendants will also be allowed.
