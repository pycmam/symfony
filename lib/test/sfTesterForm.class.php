<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * sfTesterForm implements tests for forms submitted by the user.
 *
 * @package    symfony
 * @subpackage test
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @version    SVN: $Id: sfTesterForm.class.php 24217 2009-11-22 06:47:54Z fabien $
 */
class sfTesterForm extends sfTester
{
  protected
    $forms = array(),
    $form = null;

  /**
   * Constructor.
   *
   * @param sfTestFunctionalBase $browser A browser
   * @param lime_test            $tester  A tester object
   */
  public function __construct(sfTestFunctionalBase $browser, $tester)
  {
    parent::__construct($browser, $tester);

    $this->browser->addListener('template.filter_parameters', array($this, 'filterTemplateParameters'));
  }

  /**
   * Prepares the tester.
   */
  public function prepare()
  {
    $this->form = null;
    $this->forms = array();
  }

  /**
   * Initiliazes the tester.
   */
  public function initialize()
  {
    if (!$this->forms)
    {
      $action = $this->browser->getContext()->getActionStack()->getLastEntry()->getActionInstance();
      $this->_extractForms($action->getVarHolder()->getAll());
    }
  }

  /**
   * Begins a block.
   *
   * @return sfTester This sfTester instance
   */
  public function begin($name = null)
  {
    if (null !== $name) {
      if (!isset($this->forms[$name])) {
        throw new LogicException(__METHOD__.": form with name `{$name}` not found");
      }
      $this->form = $this->forms[$name];
    }

    return parent::begin();
  }

  /**
   * Returns the current form.
   *
   * @return sfForm The current sfForm form instance
   */
  public function getForm($name = null)
  {
    if (null !== $name && isset($this->forms[$name])) {
      return $this->forms[$name];
    }
    return $this->form;
  }

  /**
   * Tests if the submitted form has some error.
   *
   * @param  Boolean|integer $value Whether to check if the form has error or not, or the number of errors
   *
   * @return sfTestFunctionalBase|sfTester
   */
  public function hasErrors($value = true)
  {
    if (null === $this->form)
    {
      throw new LogicException('no form has been submitted.');
    }

    if (is_int($value))
    {
      $this->tester->is(count($this->form->getErrorSchema()), $value, sprintf("The submitted form has `%s` errors, got:\n%s", $value, $this->form->getErrorSchema()));
    }
    else
    {
      $this->tester->is($this->form->hasErrors(), $value, sprintf("the submitted form %s, got:\n%s", ($value) ? 'has some errors' : 'is valid', $this->form->getErrorSchema()));
    }

    return $this->getObjectToReturn();
  }

  /**
   * Tests if the submitted form has a specific error.
   *
   * @param mixed $value The error message or the number of errors for the field (optional)
   *
   * @return sfTestFunctionalBase|sfTester
   */
  public function hasGlobalError($value = true)
  {
    return $this->isError(null, $value);
  }

  /**
   * Tests if the submitted form has a specific error.
   *
   * @param string $field The field name to check for an error (null for global errors)
   * @param mixed  $value The error message or the number of errors for the field (optional)
   *
   * @return sfTestFunctionalBase|sfTester
   */
  public function isError($field, $value = true)
  {
    if (null === $this->form)
    {
      throw new LogicException('no form has been submitted.');
    }

    if (null === $field)
    {
      $error = new sfValidatorErrorSchema(new sfValidatorPass(), $this->form->getGlobalErrors());
    }
    else
    {
      $error = $this->getFormField($field)->getError();
    }

    if (false === $value)
    {
      $this->tester->ok(!$error || 0 == count($error), sprintf('the submitted form has no "%s" error.', $field));
    }
    else if (true === $value)
    {
      $this->tester->ok($error && count($error) > 0, sprintf('the submitted form has a "%s" error.', $field));
    }
    else if (is_int($value))
    {
      $this->tester->ok($error && count($error) == $value, sprintf('the submitted form has %s "%s" error(s).', $value, $field));
    }
    else if (preg_match('/^(!)?([^a-zA-Z0-9\\\\]).+?\\2[ims]?$/', $value, $match))
    {
      if (!$error)
      {
        $this->tester->fail(sprintf('the submitted form has a "%s" error.', $field));
      }
      else
      {
        if ($match[1] == '!')
        {
          $this->tester->unlike($error->getCode(), substr($value, 1), sprintf('the submitted form has a "%s" error that does not match "%s".', $field, $value));
        }
        else
        {
          $this->tester->like($error->getCode(), $value, sprintf('the submitted form has a "%s" error that matches "%s".', $field, $value));
        }
      }
    }
    else
    {
      if (!$error)
      {
        $this->tester->fail(sprintf('the submitted form has a "%s" error (%s).', $field, $value));
      }
      else
      {
        $this->tester->is($error->getCode(), $value, sprintf('the submitted form has a "%s" error (%s).', $field, $value));
      }
    }

    return $this->getObjectToReturn();
  }

  /**
   * Outputs some debug information about the current submitted form.
   */
  public function debug()
  {
    if (null === $this->form)
    {
      throw new LogicException('no form has been submitted.');
    }

    print $this->tester->error('Form debug');

    print sprintf("Submitted values: %s\n", str_replace("\n", '', var_export($this->form->getTaintedValues(), true)));
    print sprintf("Errors: %s\n", $this->form->getErrorSchema());

    exit(1);
  }

  /**
   * Listens to the template.filter_parameters event to get the submitted form object.
   *
   * @param sfEvent $event      The event
   * @param array   $parameters An array of parameters passed to the template
   *
   * @return array The array of parameters passed to the template
   */
  public function filterTemplateParameters(sfEvent $event, $parameters)
  {
    if (!isset($parameters['sf_type']))
    {
      return $parameters;
    }

    if ('action' == $parameters['sf_type'])
    {
      $this->_extractForms($parameters);
    }

    return $parameters;
  }

  /**
   * @param string $path
   * @return sfFormField
   */
  public function getFormField($path)
  {
    if (false !== $pos = strpos($path, '['))
    {
      $field = $this->form[substr($path, 0, $pos)];
    }
    else
    {
      return $this->form[$path];
    }

    if (preg_match_all('/\[(?P<part>[^]]+)\]/', $path, $matches))
    {
      foreach($matches['part'] as $part)
      {
        $field = $field[$part];
      }
    }

    return $field;
  }


    /**
     * Assert form class
     *
     * @param  string $expectedClass
     * @return void
     */
    public function isInstanceOf($expectedClass)
    {
        if (null === $this->form) {
          throw new LogicException('no form has been found.');
        }

        $actualClass = get_class($this->form);
        $this->tester->is($expectedClass, $actualClass, "Expected form is instance of `{$expectedClass}`, got `{$actualClass}`");

        return $this->getObjectToReturn();
    }


    /**
     * Extract forms from array of vars
     *
     * @param  array $data
     * @return void
     */
    private function _extractForms(array $data)
    {
        foreach ($data as $name => $value) {
            if ($value instanceof sfForm) {
                $this->forms[$name] = $value;
                if ($value->isBound()) {
                    $this->form = $value;
                }
            }
        }
    }

}
