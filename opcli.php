<?php

require_once(__DIR__ . "/apiwrapper/SDK-PHP-master/src/Ontraport.php");

use OntraportAPI\Ontraport;
use OntraportAPI\ObjectType;

function getPassword($prompt = "Password")
{
    echo "{$prompt}: "; flush();
    `/bin/stty -echo`;
    $password = trim(fgets(STDIN));
    `/bin/stty echo`;
    echo "\n";

    return trim($password);
}

$app_id = getPassword("App ID");
$api_key = getPassword("API Key");

$client = new Ontraport($app_id, $api_key);
$contact_id = false;
$contact = false;
$tags = array();
$rtags = array();

echo "OntraCLI v2020-04-01\n";
for (;;)
{
    $line = readline("\n> ");
    readline_add_history($line);

    $pos = strpos($line, " ");
    if ($pos !== false)
    {
        $cmd = substr($line, 0, $pos);
        $rest = trim(substr($line, $pos + 1));
    }
    else
    {
        $cmd = $line;
        $rest = "";
    }

    $cmd = strtolower($cmd);
    $args = str_getcsv($rest);

    switch ($cmd)
    {
        case "show":
            show($args);
            break;

        case "go":
            go($args);
            break;

        case "find":
            find($args);
            break;

        case "alltags":
            alltags($args);
            break;

        case "tags":
            tags($args);
            break;

        case "addtag":
        case "deltag":
            tagop($cmd, $args);
            break;

        case "help":
            echo "OntraCLI v2020-04-01\n\n";
            echo "Commands:\n";
            echo "  find \"<search string>\": finds all contacts matching the specifed search string\n";
            echo "  go <contact id>       : goes to (makes current) the contact with the specified id\n";
            echo "  show [full]           : show some or all of the current contact's fields\n";
            echo "  alltags               : show all possible contact tags\n";
            echo "  tags                  : show the tags of the current contact\n";
            echo "  addtag <tag name>     : add the tag named <tag name> to the current contact. The tag must exist\n";
            echo "  deltag <tag name>     : remove the tag named <tag name> from the current contact\n";
            echo "  help                  : this help command\n";
            break;

        case "quit":
        case "exit":
            break 2;

        default:
            echo "Unknown command. Please make more of an effort. (Try \"help\".)\n";
    }
}

function tabulate($data, $header)
{
    $format = "";
    $vals = array();
    $linevals = array();
    foreach ($header as $field)
    {
        if ($field == "id")
        {
            $format .= "|%5.5s ";
            $linevals[] = "-------";
        }
        else
        {
            $format .= "|%-30.30s ";
            $linevals[] = "--------------------------------";
        }
        $vals[] = $field;
    }
    $format .= "\n";

    vprintf($format, $vals);
    vprintf($format, $linevals);

    foreach ($data as $datum)
    {
        $vals = array();
        foreach ($header as $field)
        {
            $vals[] = $datum[$field];
        }
        vprintf($format, $vals);
    }
}

function find($args)
{
    global $client;

    if (empty($args))
    {
        echo "find \"search string\"\n";
        return;
    }

    $search = $args[0];
    $ret = $client->contact()->retrieveMultiple(array(
        "search" => $search
    ));

    $ret = json_decode($ret, true);
    if (!empty($ret) && $ret["code"] == 0)
    {
        tabulate($ret["data"], array("id", "firstname", "lastname", "email"));
    }
    else
    {
        echo "Error. Sorry.\n";
    }
}

function go($args, $display = true)
{
    global $client;
    global $contact;

    if (empty($args))
    {
        echo "go \<contact id>\n";
        return;
    }

    $id = (int) trim($args[0]);
    if (!$id)
    {
        echo "Please enter a numeric contact id\n";
        return;
    }

    $ret = $client->contact()->retrieveSingle(array(
        "id" => $id
    ));

    $ret = json_decode($ret, true);
    if (!empty($ret) && $ret["code"] == 0)
    {
        $contact = $ret["data"];
        if ($display)
        {
            tabulate(array($contact), array("id", "firstname", "lastname", "email"));
        }
    }
    else
    {
        echo "Error. Sorry.\n";
    }
}

function show($args)
{
    global $contact;

    $full = $args && strtolower($args[0]) == "full";
    if (!$contact)
    {
        echo "Use \"go\" to select a contact by ID first. Use \"help\" more more details.\n";
        return;
    }

    if ($full)
    {
        $header = array_keys($contact);
    }
    else
    {
        $header = array("id", "firstname", "lastname", "email", "address", "city", "state", "zip");
    }

    $data = array();
    foreach ($header as $key)
    {
        $data[] = array("field" => $key, "value" => $contact[$key]);
    }

    tabulate($data, array("field", "value"));
}

function loadTags()
{
    global $client;
    global $tags;
    global $rtags;

    if (!empty($tags))
    {
        return;
    }

    // FIXME: support reloading
    $start = 0;
    for (;;)
    {
        $requestParams = array(
            "objectID"   => ObjectType::TAG,
            "sort"       => "tag_name",
            "sortDir"    => "asc",
            "start"      => $start,
        );
        $ret = $client->object()->retrieveMultiple($requestParams);

        $ret = json_decode($ret, true);
        if (!empty($ret) && $ret["code"] == 0)
        {
            $alltags = $ret["data"];
            $next = $start + count($alltags);

            // Ignore non-contact tags
            foreach ($alltags as $tag)
            {
                if ($tag["object_type_id"] == ObjectType::CONTACT)
                {
                    $tags[$tag["tag_name"]] = $tag["tag_id"];
                    $rtags[$tag["tag_id"]] = $tag["tag_name"];
                    $ctags[] = $tag;
                }
            }

            if ($next == $start)
            {
                break;
            }
            else
            {
                $start = $next;
            }
        }
        else
        {
            break;
        }
    }
}

function alltags($args)
{
    global $tags;

    loadTags();

    $data = array();
    foreach ($tags as $name => $id)
    {
        $data[] = array("tag_id" => $id, "tag_name" => $name);
    }

    tabulate($data, array("tag_id", "tag_name"));
}

function tags($args)
{
    global $contact;
    global $rtags;

    if (!$contact)
    {
        echo "Use \"go\" to select a contact by ID first. Use \"help\" more more details.\n";
        return;
    }

    loadTags();

    $ctags = explode("*/*", $contact["contact_cat"]);
    $data = array();
    foreach ($ctags as $tag_id)
    {
        if (!empty($rtags[$tag_id]))
        {
            $data[] = array("tag_id" => $tag_id, "tag_name" => $rtags[$tag_id]);
        }
    }

    tabulate($data, array("tag_id", "tag_name"));
}

function tagop($cmd, $args)
{
    global $client;
    global $contact;
    global $tags;

    $op = $cmd == "addtag" ? "add" : "remove";
    $op_past = $cmd == "addtag" ? "added" : "removed";

    if (empty($args))
    {
        echo "Usage: $cmd \"<tag name>\": $op the specified tag using the current contact.\n";
        return;
    }

    loadTags();

    $tag_id = $tags[trim($args[0])];
    if (!$tag_id)
    {
        echo "Invalid tag name. Use \"tags\" to list all tags.\n";
        return;
    }

    if (!$contact)
    {
        echo "Use \"go\" to select a contact by ID first. Use \"help\" more more details.\n";
        return;
    }

    if ($cmd === "addtag")
    {
        $requestParams = array(
            "objectID" => ObjectType::CONTACT,
            "ids"      => $contact["id"],
            "add_list" => $tag_id
        );
        $ret = $client->object()->addTag($requestParams);
    }
    else
    {
        $requestParams = array(
            "objectID" => ObjectType::CONTACT,
            "ids"      => $contact["id"],
            "remove_list" => $tag_id
        );
        $ret = $client->object()->removeTag($requestParams);
    }

    $ret = json_decode($ret, true);
    if (!empty($ret) && $ret["code"] == 0)
    {
        echo "Tag {$op_past}.\n";

        // Reload the current contact.
        go(array($contact["id"]), false);

        // Display their tags again.
        tags(false);
    }
    else
    {
        echo "Error. Sorry.\n";
    }
}