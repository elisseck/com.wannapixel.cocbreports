<?php
use CRM_Cocbreports_ExtensionUtil as E;

class CRM_Cocbreports_Form_Report_OIBMonthly extends CRM_Report_Form {

  protected $_addressField = FALSE;

  protected $_emailField = FALSE;

  protected $_summary = NULL;

  protected $_dupes = 0;

  protected $_customGroupExtends = array('Activity');
  protected $_customGroupGroupBy = FALSE;

  public function __construct() {
    $include = '';
    if (!empty($accessAllowed)) {
      $include = 'OR v.component_id IN (' . implode(', ', $accessAllowed) . ')';
    }
    $condition = " AND ( v.component_id IS NULL {$include} )";
    $this->activityTypes = CRM_Core_OptionGroup::values('activity_type', FALSE, FALSE, FALSE, NULL);
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
            //'no_display' => TRUE,
            'required' => TRUE,
            'no_repeat' => TRUE,
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
            //'no_display' => TRUE,
            'no_repeat' => TRUE,
          ),
        ),
        'order_bys' => array(
          'sort_name' => array(
            'title' => ts('Last Name, First Name'),
            'default' => '1',
            'default_weight' => '0',
            'default_order' => 'ASC',
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
            'no_display' => TRUE,
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
            'no_display' => TRUE,
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
          'fields' => [
            'record_type_id' => [
              'title' => ts('Record Type ID'),
            ],
          ],
          'filters' => [
            'record_type_id' => [
              'title' => ts('Record Type ID'),
            ],
          ],
        ],
      ),
    );
    $this->_groupFilter = TRUE;
    $this->_tagFilter = TRUE;
    parent::__construct();
  }

  public function preProcess() {
    $this->assign('reportTitle', E::ts('OIB Monthly Report'));
    parent::preProcess();
  }

  public function from() {
    $this->_from = NULL;

    $this->_from = "
         FROM civicrm_contact {$this->_aliases['civicrm_contact']} {$this->_aclFrom}
               LEFT JOIN civicrm_activity_contact
                          ON {$this->_aliases['civicrm_contact']}.id =
                             civicrm_activity_contact.contact_id
               LEFT JOIN civicrm_activity {$this->_aliases['civicrm_activity']}
                          ON civicrm_activity_contact.activity_id =
                             {$this->_aliases['civicrm_activity']}.id ";

    //$this->joinAddressFromContact();
    //$this->joinEmailFromContact();
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
    $ids = [];
    foreach ($rows as $rowNum => $row) {
      //count each contact only once who has an OIB Project Case Notes activity (there could be more than 1)
      if (!in_array($row['civicrm_contact_id'], $ids)) {
        $ids[] = $row['civicrm_contact_id'];
      }
      else {
        unset($rows[$rowNum]);
        $this->_dupes++;
      }

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

      if (array_key_exists('civicrm_contact_gender_id', $row)) {
        if ($value = $row['civicrm_contact_gender_id']) {
          $labels = civicrm_api3('Contact', 'getoptions', [
            'field' => "gender_id",
          ]);
          $rows[$rowNum]['civicrm_contact_gender_id'] = $labels['values'][$row['civicrm_contact_gender_id']];
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

    //monthly totals
    $monthlyServicesTotal = $monthlyServicesTotalUnduplicated = 0;
    $monthlyServicesTotalCount = 0;
    $groupActivity = $groupActivityUnduplicated = 0;
    $familyMember = 0;
    $totalHours = 0;

    //counties possible
    $Adams = $Alamosa = $Arapahoe = $Archuleta = $Baca = $Bent = $Boulder = $Broomfield = $Chaffee = $Cheyenne = $ClearCreek = $Conejos = $Costilla = $Crowley = $Custer = $Delta = $Denver = $Dolores = $Douglas = $Eagle = $ElPaso = $Elbert = $Fremont = $Garfield = $Gilpin = $Grand = $Gunnison = $Hinsdale = $Huerfano = $Jackson = $Jefferson = $Kiowa = $KitCarson = $LaPlata = $Lake = $Larimer = $LasAnimas = $Lincoln = $Logan = $Mesa = $Mineral = $Moffat = $Montezuma = $Montrose = $Morgan = $Otero = $Ouray = $Park = $Phillips = $Pitkin = $Prowers = $Pueblo = $RioBlanco = $RioGrande = $Routt = $Saguache = $SanJuan = $SanMiguel = $Sedgwick = $Summit = $Teller = $Washington = $Weld = $Yuma = 0;

    //Get per-row addition totals
    foreach ($rows as $row) {
      //subtotals for the top for last fiscal year and this fiscal year
      if ($row['civicrm_contact_id']) {
        $startDate = explode(' )', explode('value_case_notes_fo_30_civireport.date_of_service_206 >= ', $this->_where)[1])[0];
        $endDate = explode(' )', explode('value_case_notes_fo_30_civireport.date_of_service_206 <= ', $this->_where)[1])[0];

        $allCaseNotes = civicrm_api3('Activity', 'get', [
          'sequential' => 1,
          'target_contact_id' => $row['civicrm_contact_id'],
          'activity_type_id' => "Case Notes Form",
          'custom_209' => 2,
          'custom_206' => ['BETWEEN' => [$startDate, $endDate]],
        ]);
//reset the row specific counts in-row
        $groupActivity = $monthlyServicesTotal = 0;
        foreach ($allCaseNotes['values'] as $case) {

          //Monthly Report just counting total number of people with any service unduplicated
          if (count($case['custom_216']) > 0) {
            $monthlyServicesTotal++;
          }
          //this one row is unduplicated, so we don't need to count first here
          if (in_array('Provision of Assistive Technology Devices and Aids', $case['custom_216'])) {
            $monthlyServicesTotalCount++;
          }
          if (in_array('Assistive Technology Services', $case['custom_216'])) {
            $monthlyServicesTotalCount++;
          }
          if (in_array('Supportive Services (reader services, transportation, personal)', $case['custom_216'])) {
            $monthlyServicesTotalCount++;
          }
          if (in_array('Advocacy Training & Support Networks', $case['custom_216'])) {
            $monthlyServicesTotalCount++;
          }
          if (in_array('Counseling (peer, individual, group)', $case['custom_216'])) {
            $monthlyServicesTotalCount++;
          }
          if (in_array('Information, Referral and Community Integration', $case['custom_216'])) {
            $monthlyServicesTotalCount++;
          }
          if (in_array('Other IL Services', $case['custom_216'])) {
            $monthlyServicesTotalCount++;
          }

          if (in_array('O&M Training', $case['custom_216'])) {
            $monthlyServicesTotalCount++;
          }
          if (in_array('Communication Skills', $case['custom_216'])) {
            $monthlyServicesTotalCount++;
          }
          if (in_array('Daily Living Skills', $case['custom_216'])) {
            $monthlyServicesTotalCount++;
          }
          //group activities set to 1, 2, 3, 4, or 5 count
          if ($case['custom_214'] == 1 || $case['custom_214'] == 2 || $case['custom_214'] == 3 || $case['custom_214'] == 4 || $case['custom_214'] == 5) {
            $groupActivity++;
          }
          //family member present
          if ($case['custom_207'] == 1) {
            $familyMember++;
          }

          if ($case['custom_210']) {
            $totalHours += $case['custom_210'];
          }

        }

        if ($monthlyServicesTotal > 0) {
          $monthlyServicesTotalUnduplicated++;
        }

        if ($groupActivity > 0) {
          $groupActivityUnduplicated++;
        }

        //count up the addresses
        $address = civicrm_api3('Address', 'get', [
          'sequential' => 1,
          'contact_id' => $row['civicrm_contact_id'],
          'is_primary' => 1,
        ]);
        //there should only be 1 primary address but there could be 0 so we are careful here
        foreach ($address['values'] as $addr) {
          //generated values
          if($addr['county_id'] == 245) { $Adams++;}
          if($addr['county_id'] == 246) { $Alamosa++;}
          if($addr['county_id'] == 247) { $Arapahoe++;}
          if($addr['county_id'] == 248) { $Archuleta++;}
          if($addr['county_id'] == 249) { $Baca++;}
          if($addr['county_id'] == 250) { $Bent++;}
          if($addr['county_id'] == 251) { $Boulder++;}
          if($addr['county_id'] == 252) { $Broomfield++;}
          if($addr['county_id'] == 253) { $Chaffee++;}
          if($addr['county_id'] == 254) { $Cheyenne++;}
          if($addr['county_id'] == 255) { $ClearCreek++;}
          if($addr['county_id'] == 256) { $Conejos++;}
          if($addr['county_id'] == 257) { $Costilla++;}
          if($addr['county_id'] == 258) { $Crowley++;}
          if($addr['county_id'] == 259) { $Custer++;}
          if($addr['county_id'] == 260) { $Delta++;}
          if($addr['county_id'] == 261) { $Denver++;}
          if($addr['county_id'] == 262) { $Dolores++;}
          if($addr['county_id'] == 263) { $Douglas++;}
          if($addr['county_id'] == 264) { $Eagle++;}
          if($addr['county_id'] == 265) { $ElPaso++;}
          if($addr['county_id'] == 266) { $Elbert++;}
          if($addr['county_id'] == 267) { $Fremont++;}
          if($addr['county_id'] == 268) { $Garfield++;}
          if($addr['county_id'] == 269) { $Gilpin++;}
          if($addr['county_id'] == 270) { $Grand++;}
          if($addr['county_id'] == 271) { $Gunnison++;}
          if($addr['county_id'] == 272) { $Hinsdale++;}
          if($addr['county_id'] == 273) { $Huerfano++;}
          if($addr['county_id'] == 274) { $Jackson++;}
          if($addr['county_id'] == 275) { $Jefferson++;}
          if($addr['county_id'] == 276) { $Kiowa++;}
          if($addr['county_id'] == 277) { $KitCarson++;}
          if($addr['county_id'] == 278) { $LaPlata++;}
          if($addr['county_id'] == 279) { $Lake++;}
          if($addr['county_id'] == 280) { $Larimer++;}
          if($addr['county_id'] == 281) { $LasAnimas++;}
          if($addr['county_id'] == 282) { $Lincoln++;}
          if($addr['county_id'] == 283) { $Logan++;}
          if($addr['county_id'] == 284) { $Mesa++;}
          if($addr['county_id'] == 285) { $Mineral++;}
          if($addr['county_id'] == 286) { $Moffat++;}
          if($addr['county_id'] == 287) { $Montezuma++;}
          if($addr['county_id'] == 288) { $Montrose++;}
          if($addr['county_id'] == 289) { $Morgan++;}
          if($addr['county_id'] == 290) { $Otero++;}
          if($addr['county_id'] == 291) { $Ouray++;}
          if($addr['county_id'] == 292) { $Park++;}
          if($addr['county_id'] == 293) { $Phillips++;}
          if($addr['county_id'] == 294) { $Pitkin++;}
          if($addr['county_id'] == 295) { $Prowers++;}
          if($addr['county_id'] == 296) { $Pueblo++;}
          if($addr['county_id'] == 297) { $RioBlanco++;}
          if($addr['county_id'] == 298) { $RioGrande++;}
          if($addr['county_id'] == 299) { $Routt++;}
          if($addr['county_id'] == 300) { $Saguache++;}
          if($addr['county_id'] == 301) { $SanJuan++;}
          if($addr['county_id'] == 302) { $SanMiguel++;}
          if($addr['county_id'] == 303) { $Sedgwick++;}
          if($addr['county_id'] == 304) { $Summit++;}
          if($addr['county_id'] == 305) { $Teller++;}
          if($addr['county_id'] == 306) { $Washington++;}
          if($addr['county_id'] == 307) { $Weld++;}
          if($addr['county_id'] == 308) { $Yuma++;}

        }
      }
    }

    //Get the Total Served
    if ($statistics['counts']['rowsFound']['value']) {
      $served = abs($statistics['counts']['rowsFound']['value'] - $this->_dupes);
      $statistics['counts']['rowsFound']['value'] -= $this->_dupes;
    }
    else {
      $served = abs($statistics['counts']['rowCount']['value']);
    }
    $statistics['counts']['totalServed'] = array(
      'title' => ts('Total Number of Consumers with Intakes'),
      'value' => $served,
    );

    $statistics['counts']['monthlyServicesTotal'] = array(
      'title' => ts('Number of consumers who received individual independent living services'),
      'value' => $monthlyServicesTotalUnduplicated,
    );
    $statistics['counts']['monthlyServicesTotalCount'] = array(
      'title' => ts('Number of individual independent living services'),
      'value' => $monthlyServicesTotalCount,
    );
    $statistics['counts']['groupActivity'] = array(
      'title' => ts('Number of consumers who participated in group activities'),
      'value' => $groupActivityUnduplicated,
    );
    $statistics['counts']['familyMember'] = array(
      'title' => ts('Number of families receiving services'),
      'value' => $familyMember,
    );
    $statistics['counts']['totalHours'] = array(
      'title' => ts('Total Number of Client Contact Hours'),
      'value' => $totalHours,
    );

    //add the counties to an array and iterate over them, adding statistics if there is a count
    $allCounties = array(
      "Adams" => $Adams,
      "Alamosa" => $Alamosa,
      "Arapahoe" => $Arapahoe,
      "Archuleta" => $Archuleta,
      "Baca" => $Baca,
      "Bent" => $Bent,
      "Boulder" => $Boulder,
      "Broomfield" => $Broomfield,
      "Chaffee" => $Chaffee,
      "Cheyenne" => $Cheyenne,
      "Clear Creek" => $ClearCreek,
      "Conejos" => $Conejos,
      "Costilla" => $Costilla,
      "Crowley" => $Crowley,
      "Custer" => $Custer,
      "Delta" => $Delta,
      "Denver" => $Denver,
      "Dolores" => $Dolores,
      "Douglas" => $Douglas,
      "Eagle" => $Eagle,
      "El Paso" => $ElPaso,
      "Elbert" => $Elbert,
      "Fremont" => $Fremont,
      "Garfield" => $Garfield,
      "Gilpin" => $Gilpin,
      "Grand" => $Grand,
      "Gunnison" => $Gunnison,
      "Hinsdale" => $Hinsdale,
      "Huerfano" => $Huerfano,
      "Jackson" => $Jackson,
      "Jefferson" => $Jefferson,
      "Kiowa" => $Kiowa,
      "Kit Carson" => $KitCarson,
      "La Plata" => $LaPlata,
      "Lake" => $Lake,
      "Larimer" => $Larimer,
      "Las Animas" => $LasAnimas,
      "Lincoln" => $Lincoln,
      "Logan" => $Logan,
      "Mesa" => $Mesa,
      "Mineral" => $Mineral,
      "Moffat" => $Moffat,
      "Montezuma" => $Montezuma,
      "Montrose" => $Montrose,
      "Morgan" => $Morgan,
      "Otero" => $Otero,
      "Ouray" => $Ouray,
      "Park" => $Park,
      "Phillips" => $Phillips,
      "Pitkin" => $Pitkin,
      "Prowers" => $Prowers,
      "Pueblo" => $Pueblo,
      "Rio Blanco" => $RioBlanco,
      "Rio Grande" => $RioGrande,
      "Routt" => $Routt,
      "Saguache" => $Saguache,
      "San Juan" => $SanJuan,
      "San Miguel" => $SanMiguel,
      "Sedgwick" => $Sedgwick,
      "Summit" => $Summit,
      "Teller" => $Teller,
      "Washington" => $Washington,
      "Weld" => $Weld,
      "Yuma" => $Yuma,
    );
    foreach ($allCounties as $key => $value) {
      if ($value > 0) {
        $statistics['counts'][$key] = array(
          'title' => ts($key),
          'value' => $value,
        );
      }
    }

    $statistics['counts']['totalServedMonth'] = array(
      'title' => ts('Total Consumers served for billing month'),
      'value' => $served,
    );

    return $statistics;
  }

  /**
   * Generate where clause.
   *
   * @param bool|FALSE $durationMode
   */
  public function where($durationMode = FALSE) {
    parent::where();
    $this->_where .= "AND civicrm_activity_contact.record_type_id = 3";
    $this->_where .= " AND {$this->_aliases['civicrm_contact']}.sort_name IS NOT NULL AND {$this->_aliases['civicrm_contact']}.sort_name != ''";
  }

}
