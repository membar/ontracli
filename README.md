# Ontraport CLI

##### Ontraport Command Line Interface: Who Needs a Silly GUI?

As a backend engineer, I've never really seen the point of a user interface when using Ontraport. Here is a proof of concept for a command-line interface to the Ontraport app.

You need your App ID and Api Key to log in.

This is a proof of concept. Feel free to submit pull requests. I've included a local copy of the Ontraport API wrapper but feel free to pull a newer version, update this to use Composer, etc.

This is a personal project and in no way affiliated with Ontraport. I also will not be taking support questions about this project.

Use:

```
php opcli.php
```

...to start the command line interface.

Type `help` to get help:

```
OntraCLI v2020-04-01

Commands:
  find "<search string>": finds all contacts matching the specifed search string
  go <contact id>       : goes to (makes current) the contact with the specified id
  show [full]           : show some or all of the current contact's fields
  alltags               : show all possible contact tags
  tags                  : show the tags of the current contact
  addtag <tag name>     : add the tag named <tag name> to the current contact. The tag must exist
  deltag <tag name>     : remove the tag named <tag name> from the current contact
  help                  : this help command
```

Enjoy.
