<?php

class TestingView
{
    /**
     * Render the linkbox
     */
    public static function render_linkbox($Page) { ?>
    <div class="linkbox">
<?php      if ("classes" != $Page) { ?>
        <a href="testing.php" class="brackets">Classes</a>
<?php      }
      if ("comments" != $Page) { ?>
        <a href="testing.php?action=comments" class="brackets">Comments</a>
<?php      } ?>
    </div>
<?php  }

    /**
     * Render a list of classes
     */
    public static function render_classes($Classes) { ?>
    <table>
      <tr class="colhead">
        <td>
          Class
        </td>
        <td>
          Testable functions
        </td>
      </tr>
<?php      foreach ($Classes as $Key => $Value) {
        $Doc = Testing::get_class_comment($Key);
        $Methods = count(Testing::get_testable_methods($Key)); ?>
        <tr>
          <td>
            <a href="testing.php?action=class&amp;name=<?=$Key?>" class="tooltip" title="<?=$Doc?>"><?=$Key?></a>
          </td>
          <td>
            <?=$Methods?>
          </td>
        </tr>
<?php
    } ?>
    </table>
<?php  }

    /**
     * Render functions in a class
     */
    public static function render_functions($Methods)
    {
        foreach ($Methods as $Index => $Method) {
            $ClassName = $Method->getDeclaringClass()->getName();
            $MethodName = $Method->getName(); ?>
      <div class="box box2">
        <div class="head">
          <span><?=self::render_method_definition($Method)?></span>
          <span class="float_right">
            <a data-toggle-target="#method_params_<?=$Index?>" class="brackets">Params</a>
            <a href="#" class="brackets run" data-gazelle-id="<?=$Index?>" data-gazelle-class="<?=$ClassName?>" data-gazelle-method="<?=$MethodName?>">Run</a>
          </span>
        </div>
        <div class="pad hidden" id="method_params_<?=$Index?>">
          <?self::render_method_params($Method);?>
        </div>
        <div class="pad hidden" id="method_results_<?=$Index?>">
        </div>
      </div>
<?php
        }
    }

    /**
     * Render method parameters
     */
    private static function render_method_params($Method) { ?>
    <table>
<?php    foreach ($Method->getParameters() as $Parameter) {
        $DefaultValue = $Parameter->isDefaultValueAvailable() ? $Parameter->getDefaultValue() : ""; ?>
      <tr>
        <td class="label">
          <?=$Parameter->getName()?>
        </td>
        <td>
          <input type="text" name="<?=$Parameter->getName()?>" value="<?=$DefaultValue?>"/>
        </td>
      </tr>
<?php
    } ?>
    </table>
<?php  }

    /**
     * Render the method definition
     */
    private static function render_method_definition($Method)
    {
        $Title = "<span class='tooltip' title='" . Testing::get_method_comment($Method) . "'>" . $Method->getName() . "</span> (";
        foreach ($Method->getParameters() as $Parameter) {
            $Color = "red";
            if ($Parameter->isDefaultValueAvailable()) {
                $Color = "green";
            }
            $Title .= "<span style='color: $Color'>";
            $Title .= "$" . $Parameter->getName();
            if ($Parameter->isDefaultValueAvailable()) {
                $Title .= " = " . $Parameter->getDefaultValue();
            }
            $Title .= "</span>";
            $Title .= ", ";
        }
        $Title = rtrim($Title, ", ");
        $Title .= ")";
        return $Title;
    }

    /**
     * Renders class documentation stats
     */
    public static function render_missing_documentation($Classes) { ?>
    <table>
      <tr class="colhead">
        <td>
          Class
        </td>
        <td>
          Class documented
        </td>
        <td>
          Undocumented functions
        </td>
        <td>
          Documented functions
        </td>
      </tr>
<?php      foreach ($Classes as $Key => $Value) {
        $ClassComment = Testing::get_class_comment($Key); ?>
        <tr>
          <td>
            <?=$Key?>
          </td>
          <td>
            <?=!empty($ClassComment) ? "Yes" : "No"?>
          <td>
            <?=count(Testing::get_undocumented_methods($Key))?>
          </td>
          <td>
            <?=count(Testing::get_documented_methods($Key))?>
          </td>
        </tr>
<?php
    } ?>
    </table>
<?php  }

    /**
     * Pretty print any data
     */
    public static function render_results($Data)
    {
        $Results = '<pre><ul style="list-style-type: none">';
        if (is_array($Data)) {
            foreach ($Data as $Key => $Value) {
                if (is_array($Value)) {
                    $Results .= '<li>' . $Key . ' => ' . self::render_results($Value) . '</li>';
                } else {
                    $Results .= '<li>' . $Key . ' => ' . $Value . '</li>';
                }
            }
        } else {
            $Results .= '<li>' . $Data . '</li>';
        }
        $Results .= '</ul></pre>';
        echo $Results;
    }
}
