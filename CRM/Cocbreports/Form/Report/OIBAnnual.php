<?php
use CRM_Cocbreports_ExtensionUtil as E;

class CRM_Cocbreports_Form_Report_OIBAnnual extends CRM_Report_Form {

  protected $_addressField = FALSE;

  protected $_emailField = FALSE;

  protected $_summary = NULL;

  protected $_customGroupExtends = array('Activity');
  protected $_customGroupGroupBy = FALSE;

  public function __construct() {
    $include = '';
    if (!empty($accessAllowed)) {
      $include = 'OR v.component_id IN (' . implode(', ', $accessAllowed) . ')';
    }
    $condition = " AND ( v.component_id IS NULL {$include} )";
    $this->activityTypes = CRM_Core_OptionGroup::values('activity_type', FALSE, FALSE, FALSE, $condition);
    asort($this->activityTypes);

    $this->_columns = array(
      'civicrm_contact' => array(
        'dao' => 'CRM_Contact_DAO_Contact',
        'fields' => array(
          'sort_name' => array(
            'title' => E::ts('Contact Name'),
            'required' => TRUE,
            'default' => TRUE,
            'no_repeat' => TRUE,
          ),
          'id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
          ),
          'first_name' => array(
            'title' => E::ts('First Name'),
            'no_repeat' => TRUE,
          ),
          'last_name' => array(
            'title' => E::ts('Last Name'),
            'no_repeat' => TRUE,
          ),
          'gender_id' => array(
            'title' => E::ts('Gender'),
            'required' => TRUE,
          ),
          'birth_date' => array(
            'title' => E::ts('Birth Date'),
            'required' => TRUE,
          ),
        ),
        'filters' => array(
          'sort_name' => array(
            'title' => E::ts('Contact Name'),
            'operator' => 'like',
          ),
          'id' => array(
            'no_display' => TRUE,
          ),
        ),
        'grouping' => 'contact-fields',
      ),
      'civicrm_address' => array(
        'dao' => 'CRM_Core_DAO_Address',
        'fields' => array(
          'street_address' => NULL,
          'city' => NULL,
          'postal_code' => NULL,
          'state_province_id' => array('title' => E::ts('State/Province')),
          'country_id' => array('title' => E::ts('Country')),
        ),
        'grouping' => 'contact-fields',
      ),
      'civicrm_email' => array(
        'dao' => 'CRM_Core_DAO_Email',
        'fields' => array('email' => NULL),
        'grouping' => 'contact-fields',
      ),
      //add activity fields to filter on
      'civicrm_activity' => array(
        'dao' => 'CRM_Activity_DAO_Activity',
        'fields' => array(
          'activity_type_id' => [
            'title' => ts('Activity Type'),
            'required' => TRUE,
            'type' => CRM_Utils_Type::T_STRING,
          ],
          'activity_subject' => [
            'title' => ts('Subject'),
            'default' => TRUE,
          ],
          'activity_date_time' => [
            'title' => ts('Activity Date'),
            'required' => TRUE,
          ],
          'status_id' => [
            'title' => ts('Activity Status'),
            'default' => TRUE,
            'type' => CRM_Utils_Type::T_STRING,
          ],
        ),
        'filters' => [
          'activity_date_time' => [
            'default' => 'this.month',
            'operatorType' => CRM_Report_Form::OP_DATE,
          ],
          'activity_subject' => ['title' => ts('Activity Subject')],
          'activity_type_id' => [
            'title' => ts('Activity Type'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => $this->activityTypes,
          ],
          'status_id' => [
            'title' => ts('Activity Status'),
            'type' => CRM_Utils_Type::T_STRING,
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Core_PseudoConstant::activityStatus(),
          ],
        ],
        'civicrm_activity_contact' => [
          'dao' => 'CRM_Activity_DAO_ActivityContact',
          'fields' => [],
        ],
      ),
    );
    $this->_groupFilter = TRUE;
    $this->_tagFilter = TRUE;
    parent::__construct();
  }

  public function preProcess() {
    $this->assign('reportTitle', E::ts('OIB Annual Report'));
    parent::preProcess();
  }

  public function from() {
    $this->_from = NULL;

    $this->_from = "
         FROM  civicrm_contact {$this->_aliases['civicrm_contact']} {$this->_aclFrom}
               LEFT JOIN civicrm_activity_contact {$this->_aliases['civicrm_activity_contact']}
                          ON {$this->_aliases['civicrm_contact']}.id =
                             civicrm_activity_contact.contact_id
               LEFT JOIN civicrm_activity {$this->_aliases['civicrm_activity']}
                          ON civicrm_activity_contact.activity_id =
                             {$this->_aliases['civicrm_activity']}.id ";

    $this->joinAddressFromContact();
    $this->joinEmailFromContact();
  }

  /**
   * Add field specific select alterations.
   *
   * @param string $tableName
   * @param string $tableKey
   * @param string $fieldName
   * @param array $field
   *
   * @return string
   */
  public function selectClause(&$tableName, $tableKey, &$fieldName, &$field) {
    return parent::selectClause($tableName, $tableKey, $fieldName, $field);
  }

  /**
   * Add field specific where alterations.
   *
   * This can be overridden in reports for special treatment of a field
   *
   * @param array $field Field specifications
   * @param string $op Query operator (not an exact match to sql)
   * @param mixed $value
   * @param float $min
   * @param float $max
   *
   * @return null|string
   */
  public function whereClause(&$field, $op, $value, $min, $max) {
    return parent::whereClause($field, $op, $value, $min, $max);
  }

  public function alterDisplay(&$rows) {
    // custom code to alter rows
    $entryFound = FALSE;
    $checkList = array();
    foreach ($rows as $rowNum => $row) {

      if (!empty($this->_noRepeats) && $this->_outputMode != 'csv') {
        // not repeat contact display names if it matches with the one
        // in previous row
        $repeatFound = FALSE;
        foreach ($row as $colName => $colVal) {
          if (CRM_Utils_Array::value($colName, $checkList) &&
            is_array($checkList[$colName]) &&
            in_array($colVal, $checkList[$colName])
          ) {
            $rows[$rowNum][$colName] = "";
            $repeatFound = TRUE;
          }
          if (in_array($colName, $this->_noRepeats)) {
            $checkList[$colName][] = $colVal;
          }
        }
      }

      /*if (array_key_exists('civicrm_membership_membership_type_id', $row)) {
        if ($value = $row['civicrm_membership_membership_type_id']) {
          $rows[$rowNum]['civicrm_membership_membership_type_id'] = CRM_Member_PseudoConstant::membershipType($value, FALSE);
        }
        $entryFound = TRUE;
      }*/

      if (array_key_exists('civicrm_address_state_province_id', $row)) {
        if ($value = $row['civicrm_address_state_province_id']) {
          $rows[$rowNum]['civicrm_address_state_province_id'] = CRM_Core_PseudoConstant::stateProvince($value, FALSE);
        }
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_address_country_id', $row)) {
        if ($value = $row['civicrm_address_country_id']) {
          $rows[$rowNum]['civicrm_address_country_id'] = CRM_Core_PseudoConstant::country($value, FALSE);
        }
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_contact_sort_name', $row) &&
        $rows[$rowNum]['civicrm_contact_sort_name'] &&
        array_key_exists('civicrm_contact_id', $row)
      ) {
        $url = CRM_Utils_System::url("civicrm/contact/view",
          'reset=1&cid=' . $row['civicrm_contact_id'],
          $this->_absoluteUrl
        );
        $rows[$rowNum]['civicrm_contact_sort_name_link'] = $url;
        $rows[$rowNum]['civicrm_contact_sort_name_hover'] = E::ts("View Contact Summary for this Contact.");
        $entryFound = TRUE;
      }

      if (!$entryFound) {
        break;
      }
    }
  }

  /**
   * @param $rows
   *
   * @return array
   */
  public function statistics(&$rows) {
    $statistics = parent::statistics($rows);
    /*$totalType = $totalActivity = $totalDuration = 0;
    $query = "SELECT {$this->_tempTableName}.civicrm_activity_activity_type_id,
        {$this->_tempTableName}.civicrm_activity_id_count,
        {$this->_tempDurationSumTableName}.civicrm_activity_duration_total
    FROM {$this->_tempTableName} INNER JOIN {$this->_tempDurationSumTableName}
      ON ({$this->_tempTableName}.id = {$this->_tempDurationSumTableName}.id)";
    $actDAO = CRM_Core_DAO::executeQuery($query);
    $activityTypesCount = array();
    while ($actDAO->fetch()) {
      if (!in_array($actDAO->civicrm_activity_activity_type_id, $activityTypesCount)) {
        $activityTypesCount[] = $actDAO->civicrm_activity_activity_type_id;
      }
      $totalActivity += $actDAO->civicrm_activity_id_count;
      $totalDuration += $actDAO->civicrm_activity_duration_total;
    }
    $totalType = count($activityTypesCount);
    $statistics['counts']['type'] = array(
      'title' => ts('Total Types'),
      'value' => $totalType,
    );
    $statistics['counts']['activities'] = array(
      'title' => ts('Total Number of Activities'),
      'value' => $totalActivity,
    );
    $statistics['counts']['duration'] = array(
      'title' => ts('Total Duration (in Minutes)'),
      'value' => $totalDuration,
    );*/
    //Get the Total Served
    if ($statistics['counts']['rowsFound']['value']) {
      $served = $statistics['counts']['rowsFound']['value'];
    }
    else {
      $served = $statistics['counts']['rowCount']['value'];
    }
    $statistics['counts']['totalServed'] = array(
      'title' => ts('Total Served'),
      'value' => $served,
    );
    //Set some totals to fill
    $age55to59 = $age60to64 = $age65to69 = $age70to74 = $age75to79 = $age80to84 = $age85to89 = $age90to94 = $age95to99 = $age100plus = $ageSubTotal = 0;
    $male = $female = $otherGender = $refuseToSayGender = $genderSubTotal = 0;
    //Get per-row addition totals
    foreach ($rows as $row) {
      //birth date
      if ($row['civicrm_contact_birth_date']) {
        $birthDate = CRM_Utils_Date::customFormat($row['civicrm_contact_birth_date'], '%Y%m%d');
        if ($birthDate < date('Ymd')) {
          $age = CRM_Utils_Date::calculateAge($birthDate);
          $values['age']['y'] = CRM_Utils_Array::value('years', $age);
          switch (TRUE) {
            case in_array($values['age']['y'], range(55, 59)):
              $age55to59++;
              break;

            case in_array($values['age']['y'], range(60, 64)):
              $age60to64++;
              break;

            case in_array($values['age']['y'], range(65, 69)):
              $age65to69++;
              break;

            case in_array($values['age']['y'], range(70, 74)):
              $age70to74++;
              break;

            case in_array($values['age']['y'], range(75, 79)):
              $age75to79++;
              break;

            case in_array($values['age']['y'], range(80, 84)):
              $age80to84++;
              break;

            case in_array($values['age']['y'], range(85, 89)):
              $age85to89++;
              break;

            case in_array($values['age']['y'], range(90, 94)):
              $age90to94++;
              break;

            case in_array($values['age']['y'], range(95, 99)):
              $age95to99++;
              break;

            case ($values['age']['y'] > 100):
              $age100plus++;
              break;

            default:
              break;
          }
          //add to the subtotal either way
          $ageSubTotal++;
        }
      }
      //gender id
      if ($row['civicrm_contact_gender_id']) {
        switch ($row['civicrm_contact_gender_id']) {
          case 1:
            $female++;
            break;

          case 2:
            $male++;
            break;

          case 3:
            $otherGender++;
            break;

          case 4:
            $refuseToSayGender++;
            break;

          default:
            break;
        }
        $genderSubTotal++;
      }
    }
    return $statistics;
  }

}
