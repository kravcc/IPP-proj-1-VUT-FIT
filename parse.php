<?php


ini_set('display_errors','stderr');

if($argc == 2)
{
    if( $argv[1] == "--help")
    {
        echo "Skript typu filtr nacte ze standardniho vstupu zdrojovy
             kod v IPP-code22, zkontroluje lexikalni a syntaktickou spravnost 
             kodu a vypise na standardni vystup XML reprezentaci programu\n";
        exit (0);
    }
}
if ($argc >= 3 or $argc < 1)
{
    exit(10);
}

class XML
{
    private int $Instr;
    private string $xmlOut;
    function __construct()
    {
        $this->Instr = 1;
        $this->xmlOut = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<program language=\"IPPcode22\">\n";
    }

    function put(string $value)
    {
        $this->xmlOut = $this->xmlOut . $value;
    }
    function out()
    {
        echo str_replace("\t", "    ", $this->xmlOut);
    }
    function addInstr(string $opcode)
    {
        $this->put("\t<instruction order=\"{$this->Instr}\" opcode=\"{$opcode}\">\n");
        $this->Instr++;
    }
    function check_var(string $var, int $argnum)
    {
        if(!preg_match("/^[GLT]F@[a-zA-Z_\-\$\&\%\*\!\?][\w\-\$\&\%\*\!\?]*$/", $var))
            exit(23);
        $this->put("\t\t<arg{$argnum} type=\"var\">{$var}</arg{$argnum}>\n");   
    }
    function check_label(string $label, int $argnum)
    {
        if(!preg_match("/^[a-zA-Z_\-\$\&\%\*\!\?][\w\-\$\&\%\*\!\?]*$/", $label))
            exit(23);  
        $this->put("\t\t<arg{$argnum} type=\"label\">{$label}</arg{$argnum}>\n");
    }
    function check_symb(string $var, int $argnum)
    {
        $exp = explode('@',$var,2);

        $type = strtolower($exp[0]);
        $value = "";
        
        switch($type)
        {
        case "nil":
            if(strtolower($exp[1])!="nil")
                exit(23);
            $value = "nil";
            break;
        case "bool":
            if(strtolower($exp[1])!="true" and strtolower($exp[1])!="false" )
                exit(23);
            $value = strtolower($exp[1]);
            break;
        case "int":
            if(!preg_match("/^[\-\+\][0-9]+$/", $exp[1]))
                exit(23);
            $value = $exp[1];
            break;
        case "string":
            
            if(!preg_match("/^(?:[^\\\\]|(?:\\\\\d{3}))*$/i", $exp[1]))
                exit(23);
            $value = str_replace("&", "&amp;", $exp[1]);    
            $value = str_replace(
                    array(">","<"),
                    array("&gt;", "&lt;"),
                    $value);
            break;
        default:
            $this->check_var($var, $argnum);
            return;
        }
        $this->put("\t\t<arg{$argnum} type=\"{$type}\">{$value}</arg{$argnum}>\n");
    }
    function check_type(string $type, int $argnum)
    {
        if($type != "int" and $type != "string" and $type != "bool" )
            exit(23);
        $this->put("\t\t<arg{$argnum} type=\"type\">{$type}</arg{$argnum}>\n");   
    }
}
              
function file_parser($file)
{
    while(1){
        $line = fgets($file);
        $token = explode("#", $line);  
        $token = trim($token[0]); 
        if (!empty($token))
            break;
    }

    if(strtolower($token) != ".ippcode22")
    {
        echo "Chybna hlavicka";
        exit(21);
    }
    $xml = new XML;
    while(!feof($file))
    {
        $line = fgets($file);
        $token = explode("#", $line);
        $token = $token[0];
        $token = explode(" ", $token);
        $token[0] = strtoupper($token[0]);
        foreach($token as &$tk)
            $tk = trim($tk);
        unset($tk);
        if(empty($token[0]))continue;
        
        if (($token[0] == "DEFVAR") or ($token[0] == "POPS"))
        {
            $xml->addInstr($token[0]);
            $xml->check_var($token[1], 1);
            $xml->put("\t</instruction>\n");
        }
        elseif(($token[0] == "CALL") or ($token[0] == "LABEL") or ($token[0] == "JUMP"))
        {
            $xml->addInstr($token[0]);
            $xml->check_label($token[1], 1);
            $xml->put("\t</instruction>\n");
        }
        elseif(($token[0] == "PUSHS") or ($token[0] == "WRITE") or ($token[0] == "EXIT") or ($token[0] == "DPRINT"))
        {
            $xml->addInstr($token[0]);
            $xml->check_symb($token[1], 1);
            $xml->put("\t</instruction>\n");
        }
        elseif($token[0] == "READ")
        {
            $xml->addInstr($token[0]);
            $xml->check_var($token[1], 1);
            $xml->check_type($token[2], 2);
            $xml->put("\t</instruction>\n");
        }
        elseif(($token[0] == "MOVE") or ($token[0] == "NOT") or ($token[0] == "INT2CHAR")
        or ($token[0] == "TYPE") or ($token[0] == "STRLEN"))
        {
            $xml->addInstr($token[0]);
            $xml->check_var($token[1], 1);
            $xml->check_symb($token[2], 2);
            $xml->put("\t</instruction>\n");
        }
        elseif(($token[0] == "ADD") or ($token[0] == "SUB") or ($token[0] == "MUL") or ($token[0] == "IDIV")
                or ($token[0] == "LT") or ($token[0] == "GT")or ($token[0] == "EQ") or ($token[0] == "AND")
                or ($token[0] == "OR") or ($token[0] == "STRI2INT")or ($token[0] == "GETCHAR") or ($token[0] == "SETCHAR")
            or ($token[0] == "CONCAT"))
        {
            $xml->addInstr($token[0]);
            $xml->check_var($token[1], 1);
            $xml->check_symb($token[2], 2);
            $xml->check_symb($token[3], 3);
            $xml->put("\t</instruction>\n");
        }
        elseif(($token[0] == "JUMPIFNEQ") or ($token[0] == "JUMPIFEQ"))
        {
            $xml->addInstr($token[0]);
            $xml->check_label($token[1], 1);
            $xml->check_symb($token[2], 2);
            $xml->check_symb($token[3], 3);
            $xml->put("\t</instruction>\n");
        }
        elseif(($token[0] == "CREATEFRAME") or ($token[0] == "PUSHFRAME")
            or ($token[0] == "POPFRAME") or ($token[0] == "BREAK") or ($token[0] == "RETURN"))
        {
            $xml->addInstr($token[0]);
            $xml->put("\t</instruction>\n");
        }
        else
        {
            exit(22);
        }
    }
    $xml->put("</program>\n");
    $xml->out();
}

/**
 * Prace se souborem
 * @param mixed $argc
 * @param mixed $argv
 */
function check_argc($argc, $argv){
    $file = STDIN;
    if ($file == null){
        exit(11);
    }
    file_parser($file);
}


check_argc($argc, $argv);

?>
