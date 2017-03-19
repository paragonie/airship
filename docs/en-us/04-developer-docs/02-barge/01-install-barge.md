# Installing `barge`

```sh
git clone https://github.com/paragonie/airship-barge.git
cd airship-barge
sudo ln -s ./barge /usr/bin/barge
```

To install [`barge`](https://github.com/paragonie/airship-barge), first clone
the Github repository, then create a symlink from `/usr/bin/barge` to the
`barge` file in the project's root directory.

Alternatively, you could create a shell function (e.g. in your `.bashrc` file).

When a new version of `barge` is released, just `cd` into the `airship-barge`
directory and run `git pull origin master`.
