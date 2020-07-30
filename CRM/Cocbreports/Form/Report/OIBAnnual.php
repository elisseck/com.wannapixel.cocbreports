<?php
use CRM_Cocbreports_ExtensionUtil as E;

class CRM_Cocbreports_Form_Report_OIBAnnual extends CRM_Report_Form {

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
    $this->assign('reportTitle', E::ts('OIB Annual Report'));
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
    //make sure total matches the duplicates we took away
    $statistics['counts']['rowsFound']['value'] -= $this->_dupes;
    $previousFy = $notPreviousFy = 0;
    //Set some totals to fill
    $age55to59 = $age60to64 = $age65to69 = $age70to74 = $age75to79 = $age80to84 = $age85to89 = $age90to94 = $age95to99 = $age100plus = $ageSubTotal = 0;
    $male = $female = $otherGender = $refuseToSayGender = $genderSubTotal = 0;
    $whiteNonHispanic = $hispanicLatino = $blackAfricanAmerican = $asian = $americanIndianAlaskanNative = $nativeHawaiian = $otherRace = $twoOrMoreRaces = $unknownRace = $refuseToAnswerRace = $raceSubTotal = 0;
    $totallyBlind = $legallyBlind = $severeVisualImpairment = $blindSubTotal = 0;
    $macularDegeneration = $diabeticRetinopathy = $glaucoma = $cataracts = $otherCause = $causeSubTotal = 0;
    $hearingImpairment = $communicationImpairment = $diabetes = $cardiovascular = $cancer = $movementDisorders = $mobilityImpairment = $alzheimers = $cognitiveImpairment = $depression = $mentalHealthImpairment = $otherConcerns = $otherImpairmentsSubTotal = 0;
    $private = $seniorLiving = $assisted = $nursingHome = $homeless = $residenceSubTotal = 0;
    $eyeProvider = $physician = $state = $government = $veterans = $seniorCenter = $assistedLivingRef = $nursingHomeRef = $faithBased = $independentLiving = $familyMember = $selfRef = $otherRef = $referralSubTotal = 0;

    //case notes fields
    $provisionAssistiveDevices = $provisionAssistiveTech = $orientationAndMobilityTraining = $communicationSkills = $mobilitySkills = $dailyLivingSkills = $supportiveServices = $advocacyTraining = $counseling = $communityIntegration = $otherILServices = 0;

    //performance metrics fields
    $receivingAtServices = $atServicesAndImproved = $atServicesNotDetermined = $oAndMTraining = $oAndMImproved = $oAndMNotDetermined = $commsTraining = $commsTrainingImproved = $commsTrainingNotDetermined = $dailyLivingTraining = $dailyLivingTrainingImproved = $dailyLivingTrainingNotDetermined = 0;

    //case closure fields
    $greaterConfidence = $lessConfidence = $noChange = $unrelatedChange = $died = $totalCaseClosures = $totalCaseClosuresWithImprovement = 0;

    //Get per-row addition totals
    foreach ($rows as $row) {
      //subtotals for the top for last fiscal year and this fiscal year
      if ($row['civicrm_contact_id']) {
        $startDate = explode(' )', explode('value_case_notes_fo_30_civireport.date_of_service_206 >= ', $this->_where)[1])[0];
        $endDate = explode(' )', explode('value_case_notes_fo_30_civireport.date_of_service_206 <= ', $this->_where)[1])[0];
        $startDateMinusOneYear = date('Y-m-d', strtotime("-1 year", strtotime($startDate)));
        $endDateMinusOneYear = date('Y-m-d', strtotime("-1 year", strtotime($endDate)));
        $caseNotes = civicrm_api3('Activity', 'get', [
          'sequential' => 1,
          'target_contact_id' => $row['civicrm_contact_id'],
          'activity_type_id' => "Case Notes Form",
          'custom_206' => ['BETWEEN' => [$startDateMinusOneYear, $endDateMinusOneYear]],
        ]);
        if ($caseNotes['count'] > 0) {
          $previousFy++;
        }
        else {
          $notPreviousFy++;
        }
      }
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

      //senior intake activity-based stats
      if ($row['civicrm_contact_id']) {
        //get the senior intake activity, there should only be one of these
        $result = civicrm_api3('Activity', 'get', [
          'sequential' => 1,
          'target_contact_id' => $row['civicrm_contact_id'],
          'activity_type_id' => "Senior Intake Form",
        ]);
        //race-ethnicity
        //$whiteNonHispanic = $hispanicLatino = $blackAfricanAmerican = $asian = $americanIndianAlaskanNative = $nativeHawaiian = $otherRace = $twoOrMoreRaces = $unknownRace = $refuseToAnswerRace = $raceSubTotal = 0;
        if ($result['values'][0]['custom_159']) {
          switch ($result['values'][0]['custom_159']) {
            case 1:
              $whiteNonHispanic++;
              break;

            case 2:
              $blackAfricanAmerican++;
              break;

            case 10:
              $hispanicLatino++;
              break;

            case 3:
              $asian++;
              break;

            case 4:
              $americanIndianAlaskanNative++;
              break;

            case 5:
              $nativeHawaiian++;
              break;

            case 6:
              $otherRace++;
              break;

            case 7:
              $twoOrMoreRaces++;
              break;

            case 8:
              $unknownRace++;
              break;

            case 9:
              $refuseToAnswerRace++;
              break;

            default:
              break;

          }
          //add to subtotal
          $raceSubTotal++;
        }
        //degree of visual impairment
        //$totallyBlind = $legallyBlind = $severeVisualImpairment = $blindSubTotal = 0;
        if ($result['values'][0]['custom_171']) {
          switch ($result['values'][0]['custom_171']) {
            case 1:
              $totallyBlind++;
              break;

            case 2:
              $legallyBlind++;
              break;

            case 3:
              $severeVisualImpairment++;
              break;

            default:
              break;
          }
          //add to subtotal
          $blindSubTotal++;
        }

        //major cause of visual impairment
        //$macularDegeneration = $diabeticRetinopathy = $glaucoma = $cataracts = $otherCause = $causeSubTotal = 0;
        if ($result['values'][0]['custom_172']) {
          switch ($result['values'][0]['custom_172']) {
            case 1:
              $macularDegeneration++;
              break;

            case 2:
              $diabeticRetinopathy++;
              break;

            case 3:
              $glaucoma++;
              break;

            case 4:
              $cataracts++;
              break;

            case 5:
              $otherCause++;
              break;

            case 6:
              $otherCause++;
              break;

            case 7:
              $otherCause++;
              break;

            case 8:
              $otherCause++;
              break;

            case 9:
              $otherCause++;
              break;

            case 10:
              $otherCause++;
              break;

            case 11:
              $otherCause++;
              break;

            case 12:
              $otherCause++;
              break;

            default:
              break;
          }
          //add to subtotal
          $causeSubTotal++;
        }

        //other age-related impairments
        //$hearingImpairment = $communicationImpairment = $diabetes = $cardiovascular = $cancer = $movementDisorders = $mobilityImpairment = $alzheimers = $cognitiveImpairment = $depression = $mentalHealthImpairment = $otherConcerns;
        if ($result['values'][0]['custom_175']) {
          switch (TRUE) {
            case in_array('Hearing Impairment', $result['values'][0]['custom_175']):
              $hearingImpairment++;
              break;

            case in_array('Communication Impairment', $result['values'][0]['custom_175']):
              $communicationImpairment++;
              break;

            case in_array('Diabetes', $result['values'][0]['custom_175']):
              $diabetes++;
              break;

            case in_array('Cardiovascular Disease & Strokes', $result['values'][0]['custom_175']):
              $cardiovascular++;
              break;

            case in_array('Cancer', $result['values'][0]['custom_175']):
              $cancer++;
              break;

            case in_array('Bone Muscle Skin Joint Movement Disorders', $result['values'][0]['custom_175']):
              $movementDisorders++;
              break;

            case in_array('Mobility Impairment', $result['values'][0]['custom_175']):
              $mobilityImpairment++;
              break;

            case in_array('Alzheimer\'s Disease or Cognitive Impairment', $result['values'][0]['custom_175']):
              $alzheimers++;
              break;

            case in_array('Cognitive or Intellectual Impairment', $result['values'][0]['custom_175']):
              $cognitiveImpairment++;
              break;

            case in_array('Depression or Mood Disorders', $result['values'][0]['custom_175']):
              $depression++;
              break;

            case in_array('Mental Health Impairment', $result['values'][0]['custom_175']):
              $mentalHealthImpairment++;
              break;

            case in_array('Other Major Geriatric Concerns', $result['values'][0]['custom_175']):
              $otherConcerns++;
              break;

            default:
              break;
          }

        }

        //type of residence
        //$private = $seniorLiving = $assisted = $nursingHome = $homeless = $residenceSubTotal = 0;
        if ($result['values'][0]['custom_165']) {
          switch ($result['values'][0]['custom_165']) {
            case 1:
              $private++;
              break;

            case 2:
              $seniorLiving++;
              break;

            case 3:
              $assisted++;
              break;

            case 4:
              $nursingHome++;
              break;

            case 5:
              $homeless++;
              break;

            default:
              break;
          }
          //add to subtotal
          $residenceSubTotal++;
        }

        //source of referral
        //$eyeProvider = $physician = $state = $government = $veterans = $seniorCenter = $assistedLivingRef = $nursingHomeRef = $faithBased = $independentLiving = $familyMember = $selfRef = $otherRef = $referralSubTotal = 0;
        if ($result['values'][0]['custom_172']) {
          switch ($result['values'][0]['custom_172']) {
            case 1:
              $eyeProvider++;
              break;

            case 2:
              $physician++;
              break;

            case 3:
              $state++;
              break;

            case 4:
              $government++;
              break;

            case 5:
              $veterans++;
              break;

            case 6:
              $seniorCenter++;
              break;

            case 7:
              $assistedLivingRef++;
              break;

            case 8:
              $nursingHomeRef++;
              break;

            case 9:
              $faithBased++;
              break;

            case 10:
              $independentLiving++;
              break;

            case 11:
              $familyMember++;
              break;

            case 12:
              $selfRef++;
              break;

            case 13:
              $otherRef++;
              break;

            case 14:
              $otherRef++;
              break;

            case 15:
              $otherRef++;
              break;

            case 16:
              $otherRef++;
              break;

            case 17:
              $otherRef++;
              break;

            case 18:
              $otherRef++;
              break;

            case 19:
              $otherRef++;
              break;

            case 20:
              $otherRef++;
              break;

            case 21:
              $otherRef++;
              break;

            default:
              break;
          }
          //add to subtotal
          $referralSubTotal++;
        }
      }

      if ($row['civicrm_contact_id']) {
        $allCaseNotes = civicrm_api3('Activity', 'get', [
          'sequential' => 1,
          'target_contact_id' => $row['civicrm_contact_id'],
          'activity_type_id' => "Case Notes Form",
          'custom_206' => ['BETWEEN' => [$startDate, $endDate]],
        ]);

        //case notes fields
        //$provisionAssistiveDevices = $provisionAssistiveTech = $orientationAndMobilityTraining = $communicationSkills = $mobilitySkills = $dailyLivingSkills = $supportiveServices = $advocacyTraining = $counseling = $communityIntegration = $otherILServices = 0;

        //performance metrics from case notes
        //$receivingAtServices = $atServicesAndImproved = $atServicesNotDetermined = $oAndMTraining = $oAndMImproved = $oAndMNotDetermined = $commsTraining = $commsTrainingImproved = $commsTrainingNotDetermined = $dailyLivingTraining = $dailyLivingTrainingImproved = $dailyLivingTrainingNotDetermined = 0;
        //set up some counts to keep track if this contact was counted at all or not so we don't double up
        //these may be useful later if they wanted to add totals
        $receivingAtServicesCount = $atServicesAndImprovedCount = $oAndMTrainingCount = $oAndMImprovedCount = $commsTrainingCount = $commsTrainingImprovedCount = $dailyLivingTrainingCount = $dailyLivingTrainingImprovedCount = 0;
        $provisionAssistiveTechCount = $orientationAndMobilityTrainingCount = $communicationSkillsCount = $dailyLivingSkillsCount = $supportiveServicesCount = $advocacyTrainingCount = $counselingCount = $communityIntegrationCount = $otherILServicesCount = 0;
        foreach ($allCaseNotes['values'] as $case) {
          //this one row is unduplicated, so we don't need to count first here
          if (in_array('Provision of Assistive Technology Devices and Aids', $case['custom_216'])) {
            $provisionAssistiveDevices++;
          }
          if (in_array('Assistive Technology Services', $case['custom_216'])) {
            $provisionAssistiveTechCount++;
          }
          if (in_array('Supportive Services (reader services, transportation, personal)', $case['custom_216'])) {
            $supportiveServicesCount++;
          }
          if (in_array('Advocacy Training & Support Networks', $case['custom_216'])) {
            $advocacyTrainingCount++;
          }
          if (in_array('Counseling (peer, individual, group)', $case['custom_216'])) {
            $counselingCount++;
          }
          if (in_array('Information, Referral and Community Integration', $case['custom_216'])) {
            $communityIntegrationCount++;
          }
          if (in_array('Other IL Services', $case['custom_216'])) {
            $otherILServicesCount++;
          }
          if ($case['custom_214'] == 2 || $case['custom_214'] == 3 || $case['custom_214'] == 6 || $case['custom_214'] == 7) {
            if (in_array('Provision of Assistive Technology Devices and Aids', $case['custom_216']) || in_array('Assistive Technology Services', $case['custom_216'])) {
              $receivingAtServicesCount++;
            }
          }
          if (in_array('Participant receiving Technology Services demonstrated improvement in abilities', $case['custom_216'])) {
            $atServicesAndImprovedCount++;
          }
          if (in_array('O&M Training', $case['custom_216'])) {
            $oAndMTrainingCount++;
            //some items on this report were duplicate requests
            $orientationAndMobilityTrainingCount++;
          }
          if (in_array('Participant receiving O&M services demonstrated improvement in abilities', $case['custom_216'])) {
            $oAndMImprovedCount++;
          }
          if (in_array('Communication Skills', $case['custom_216'])) {
            $commsTrainingCount++;
            $communicationSkillsCount++;
          }
          if (in_array('Participant receiving CommunicationTraining demonstrated improvement in abilities', $case['custom_216'])) {
            $commsTrainingImprovedCount++;
          }
          if (in_array('Daily Living Skills', $case['custom_216'])) {
            $dailyLivingTrainingCount++;
            $dailyLivingSkillsCount++;
          }
          if (in_array('Participant receiving Daily Living services demonstrated improvement in abilities', $case['custom_216'])) {
            $dailyLivingTrainingImprovedCount++;
          }
        }
        //tally up the 'one per contact' case notes including 'functional gains not determined'
        //$provisionAssistiveTechCount = $orientationAndMobilityTrainingCount = $communicationSkillsCount = $dailyLivingSkillsCount = $supportiveServicesCount = $advocacyTrainingCount = $counselingCount = $communityIntegrationCount = $otherILServicesCount = 0;
        if ($provisionAssistiveTechCount > 0) {
          $provisionAssistiveTech++;
        }
        if ($orientationAndMobilityTrainingCount > 0) {
          $orientationAndMobilityTraining++;
        }
        if ($communicationSkillsCount > 0) {
          $communicationSkills++;
        }
        if ($dailyLivingSkillsCount > 0) {
          $dailyLivingSkills++;
        }
        if ($supportiveServicesCount > 0) {
          $supportiveServices++;
        }
        if ($advocacyTrainingCount > 0) {
          $advocacyTraining++;
        }
        if ($counselingCount > 0) {
          $counseling++;
        }
        if ($communityIntegrationCount > 0) {
          $communityIntegration++;
        }
        if ($otherILServicesCount > 0) {
          $otherILServices++;
        }
        //$receivingAtServices = $atServicesAndImproved = $atServicesNotDetermined = $oAndMTraining = $oAndMImproved = $oAndMNotDetermined = $commsTraining = $commsTrainingImproved = $commsTrainingNotDetermined = $dailyLivingTraining = $dailyLivingTrainingImproved = $dailyLivingTrainingNotDetermined = 0;
        if ($receivingAtServicesCount > 0) {
          $receivingAtServices++;
        }
        //If improved, add here, otherwise results were not determined
        if ($atServicesAndImprovedCount > 0) {
          $atServicesAndImproved++;
        }
        else {
          $atServicesNotDetermined++;
        }

        if ($oAndMTrainingCount > 0) {
          $oAndMTraining++;
        }
        if ($oAndMImprovedCount > 0) {
          $oAndMImproved++;
        }
        else {
          $oAndMNotDetermined++;
        }

        if ($commsTrainingCount > 0) {
          $commsTraining++;
        }
        if ($commsTrainingImprovedCount > 0) {
          $commsTrainingImproved++;
        }
        else {
          $commsTrainingNotDetermined++;
        }

        if ($dailyLivingTrainingCount > 0) {
          $dailyLivingTraining++;
        }
        if ($dailyLivingTrainingImprovedCount > 0) {
          $dailyLivingTrainingImproved++;
        }
        else {
          $dailyLivingTrainingNotDetermined++;
        }

        //case closure fields
        $caseClosure = civicrm_api3('Activity', 'get', [
          'sequential' => 1,
          'target_contact_id' => $row['civicrm_contact_id'],
          'activity_type_id' => "Case Closure Form",
          'custom_219' => ['BETWEEN' => [$startDate, $endDate]],
        ]);
        if ($caseClosure['count'] > 0) {
          $totalCaseClosures++;
        }
        //there should only be one as noted in spec but we're still careful here
        foreach ($caseClosure['values'] as $closure) {
          //case closure fields
          //$greaterConfidence = $lessConfidence = $noChange = $unrelatedChange = $died = $totalCaseClosures = $totalCaseClosuresWithImprovement = 0;
          switch ($closure['custom_222']) {
            case 'Greater Control or Confidence':
              $greaterConfidence++;
              break;

            case 'Less Control':
              $lessConfidence++;
              break;

            case 'No Change':
              $noChange++;
              break;

            default:
              break;
          }

          switch ($closure['custom_223']) {
            case 1:
              $unrelatedChange++;
              break;

            case 2:
              $died++;
              break;

            default:
              break;
          }

          if (in_array('Participant believes they now can live more independently due to services received', $closure['custom_221']) || in_array('Participant believes they can maintain their current living situation', $closure['custom_221'])) {
            $totalCaseClosuresWithImprovement++;
          }
        }
      }
    }
    //totals
    //Number of Individuals who began receiving services in previous FY and continued to receive services in the reported FY (with Intakes)
    $statistics['counts']['previousFy'] = array(
      'title' => ts('Individuals who began receiving services in previous FY and continued to receive services in the reported FY'),
      'value' => $previousFy,
    );

    //Number of individuals who began receiving services in the reported FY
    $statistics['counts']['notPreviousFy'] = array(
      'title' => ts('Number of individuals who began receiving services in the reported FY'),
      'value' => $notPreviousFy,
    );

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

    //add age stats
    $statistics['counts']['age55to59'] = array(
      'title' => ts('Age 55 to 59'),
      'value' => $age55to59,
    );
    $statistics['counts']['age60to64'] = array(
      'title' => ts('Age 60 to 64'),
      'value' => $age60to64,
    );
    $statistics['counts']['age65to69'] = array(
      'title' => ts('Age 65 to 69'),
      'value' => $age65to69,
    );
    $statistics['counts']['age70to74'] = array(
      'title' => ts('Age 70 to 74'),
      'value' => $age70to74,
    );
    $statistics['counts']['age75to79'] = array(
      'title' => ts('Age 75 to 79'),
      'value' => $age75to79,
    );
    $statistics['counts']['age80to84'] = array(
      'title' => ts('Age 80 to 84'),
      'value' => $age80to84,
    );
    $statistics['counts']['age85to89'] = array(
      'title' => ts('Age 85 to 89'),
      'value' => $age85to89,
    );
    $statistics['counts']['age90to94'] = array(
      'title' => ts('Age 90 to 94'),
      'value' => $age90to94,
    );
    $statistics['counts']['age95to99'] = array(
      'title' => ts('Age 95 to 99'),
      'value' => $age95to99,
    );
    $statistics['counts']['age100plus'] = array(
      'title' => ts('Age 100 and Over'),
      'value' => $age100plus,
    );
    $statistics['counts']['ageSubTotal'] = array(
      'title' => ts('Total Served (Age)'),
      'value' => $ageSubTotal,
    );

    //add gender stats
    $statistics['counts']['female'] = array(
      'title' => ts('Female'),
      'value' => $female,
    );
    $statistics['counts']['male'] = array(
      'title' => ts('Male'),
      'value' => $male,
    );
    $statistics['counts']['otherGender'] = array(
      'title' => ts('Other Gender'),
      'value' => $otherGender,
    );
    $statistics['counts']['refuseToSayGender'] = array(
      'title' => ts('Refuse to Say Gender'),
      'value' => $refuseToSayGender,
    );
    $statistics['counts']['genderSubTotal'] = array(
      'title' => ts('Total Served (Gender)'),
      'value' => $genderSubTotal,
    );

    //senior intake activity-based stats
    //race-ethnicity
    //$whiteNonHispanic = $hispanicLatino = $blackAfricanAmerican = $asian = $americanIndianAlaskanNative = $nativeHawaiian = $otherRace = $twoOrMoreRaces = $unknownRace = $refuseToAnswerRace = $raceSubTotal = 0;
    $statistics['counts']['whiteNonHispanic'] = array(
      'title' => ts('White Non-Hispanic'),
      'value' => $whiteNonHispanic,
    );
    $statistics['counts']['hispanicLatino'] = array(
      'title' => ts('Hispanic/Latino'),
      'value' => $hispanicLatino,
    );
    $statistics['counts']['blackAfricanAmerican'] = array(
      'title' => ts('Black/African American'),
      'value' => $blackAfricanAmerican,
    );
    $statistics['counts']['asian'] = array(
      'title' => ts('Asian'),
      'value' => $asian,
    );
    $statistics['counts']['americanIndianAlaskanNative'] = array(
      'title' => ts('American Indian / Alaskan Native'),
      'value' => $americanIndianAlaskanNative,
    );
    $statistics['counts']['nativeHawaiian'] = array(
      'title' => ts('Native Hawaiian or Pacific Islander'),
      'value' => $nativeHawaiian,
    );
    $statistics['counts']['otherRace'] = array(
      'title' => ts('Other Race'),
      'value' => $otherRace,
    );
    $statistics['counts']['twoOrMoreRaces'] = array(
      'title' => ts('Two or More Races'),
      'value' => $twoOrMoreRaces,
    );
    $statistics['counts']['unknownRace'] = array(
      'title' => ts('Unknown Race'),
      'value' => $unknownRace,
    );
    $statistics['counts']['refuseToAnswerRace'] = array(
      'title' => ts('Refuse to Answer Race'),
      'value' => $refuseToAnswerRace,
    );
    $statistics['counts']['raceSubTotal'] = array(
      'title' => ts('Total Served (Race)'),
      'value' => $raceSubTotal,
    );

    //degree of visual impairment
    //$totallyBlind = $legallyBlind = $severeVisualImpairment = $blindSubTotal = 0;
    $statistics['counts']['totallyBlind'] = array(
      'title' => ts('Totally Blind'),
      'value' => $totallyBlind,
    );
    $statistics['counts']['legallyBlind'] = array(
      'title' => ts('Legally Blind'),
      'value' => $legallyBlind,
    );
    $statistics['counts']['severeVisualImpairment'] = array(
      'title' => ts('Severe Visual Impairment'),
      'value' => $severeVisualImpairment,
    );
    $statistics['counts']['blindSubTotal'] = array(
      'title' => ts('Total Served (Blindness)'),
      'value' => $blindSubTotal,
    );

    //major cause of visual impairment
    //$macularDegeneration = $diabeticRetinopathy = $glaucoma = $cataracts = $otherCause = $causeSubTotal = 0;
    $statistics['counts']['macularDegeneration'] = array(
      'title' => ts('Macular Degeneration'),
      'value' => $macularDegeneration,
    );
    $statistics['counts']['diabeticRetinopathy'] = array(
      'title' => ts('Diabetic Retinopathy'),
      'value' => $diabeticRetinopathy,
    );
    $statistics['counts']['glaucoma'] = array(
      'title' => ts('Glaucoma'),
      'value' => $glaucoma,
    );
    $statistics['counts']['cataracts'] = array(
      'title' => ts('Cataracts'),
      'value' => $cataracts,
    );
    $statistics['counts']['otherCause'] = array(
      'title' => ts('Other Cause'),
      'value' => $otherCause,
    );
    $statistics['counts']['causeSubTotal'] = array(
      'title' => ts('Total Served (Cause)'),
      'value' => $causeSubTotal,
    );

    //other age-related impairments
    //$hearingImpairment = $communicationImpairment = $diabetes = $cardiovascular = $cancer = $movementDisorders = $mobilityImpairment = $alzheimers = $cognitiveImpairment = $depression = $mentalHealthImpairment = $otherConcerns = 0;
    $statistics['counts']['hearingImpairment'] = array(
      'title' => ts('Hearing Impairment'),
      'value' => $hearingImpairment,
    );
    $statistics['counts']['communicationImpairment'] = array(
      'title' => ts('Communication Impairment'),
      'value' => $communicationImpairment,
    );
    $statistics['counts']['diabetes'] = array(
      'title' => ts('Diabetes'),
      'value' => $diabetes,
    );
    $statistics['counts']['cardiovascular'] = array(
      'title' => ts('Cardiovascular Disease & Strokes'),
      'value' => $cardiovascular,
    );
    $statistics['counts']['cancer'] = array(
      'title' => ts('Cancer'),
      'value' => $cancer,
    );
    $statistics['counts']['movementDisorders'] = array(
      'title' => ts('Bone, Muscle, Skin, Joint, Movement Disorders'),
      'value' => $movementDisorders,
    );
    $statistics['counts']['mobilityImpairment'] = array(
      'title' => ts('Mobility Impairment'),
      'value' => $mobilityImpairment,
    );
    $statistics['counts']['alzheimers'] = array(
      'title' => ts('Alzheimer\'s Disease/Cognitive Impairment'),
      'value' => $alzheimers,
    );
    $statistics['counts']['cognitiveImpairment'] = array(
      'title' => ts('Cognitive or Intellectual Impairment'),
      'value' => $cognitiveImpairment,
    );
    $statistics['counts']['depression'] = array(
      'title' => ts('Depression/Mood Disorders'),
      'value' => $depression,
    );
    $statistics['counts']['mentalHealthImpairment'] = array(
      'title' => ts('Mental Health Impairment'),
      'value' => $mentalHealthImpairment,
    );
    $statistics['counts']['otherConcerns'] = array(
      'title' => ts('Other Concerns'),
      'value' => $otherConcerns,
    );

    //type of residence
    //$private = $seniorLiving = $assisted = $nursingHome = $homeless = $residenceSubTotal = 0;
    $statistics['counts']['private'] = array(
      'title' => ts('Private Residence'),
      'value' => $private,
    );
    $statistics['counts']['seniorLiving'] = array(
      'title' => ts('Senior Living/Retirement Community'),
      'value' => $seniorLiving,
    );
    $statistics['counts']['assisted'] = array(
      'title' => ts('Assisted Living Facility'),
      'value' => $assisted,
    );
    $statistics['counts']['nursingHome'] = array(
      'title' => ts('Nursing Home/Long Term Care Facility'),
      'value' => $nursingHome,
    );
    $statistics['counts']['homeless'] = array(
      'title' => ts('Homeless'),
      'value' => $homeless,
    );
    $statistics['counts']['residenceSubTotal'] = array(
      'title' => ts('Total Served (Residence)'),
      'value' => $residenceSubTotal,
    );

    //source of referral
    //$eyeProvider = $physician = $state = $government = $veterans = $seniorCenter = $assistedLivingRef = $nursingHomeRef = $faithBased = $independentLiving = $familyMember = $selfRef = $otherRef = $referralSubTotal = 0;
    $statistics['counts']['eyeProvider'] = array(
      'title' => ts('Eye Care Provider (Ophthalmologist, Optometrist)'),
      'value' => $eyeProvider,
    );
    $statistics['counts']['physician'] = array(
      'title' => ts('Physician/Medical Provider'),
      'value' => $physician,
    );
    $statistics['counts']['state'] = array(
      'title' => ts('State VR Agency'),
      'value' => $state,
    );
    $statistics['counts']['government'] = array(
      'title' => ts('Government or Social Service Agency'),
      'value' => $government,
    );
    $statistics['counts']['veterans'] = array(
      'title' => ts('Veterans Administration'),
      'value' => $veterans,
    );
    $statistics['counts']['seniorCenter'] = array(
      'title' => ts('Senior Center'),
      'value' => $seniorCenter,
    );
    $statistics['counts']['assistedLivingRef'] = array(
      'title' => ts('Assisted Living Facility'),
      'value' => $assistedLivingRef,
    );
    $statistics['counts']['nursingHomeRef'] = array(
      'title' => ts('Nursing Home/Long Term Care Facility'),
      'value' => $nursingHomeRef,
    );
    $statistics['counts']['faithBased'] = array(
      'title' => ts('Faith Based Organization'),
      'value' => $faithBased,
    );
    $statistics['counts']['independentLiving'] = array(
      'title' => ts('Independent Living Center'),
      'value' => $independentLiving,
    );
    $statistics['counts']['familyMember'] = array(
      'title' => ts('Family Member or Friend'),
      'value' => $familyMember,
    );
    $statistics['counts']['selfRef'] = array(
      'title' => ts('Self Referral'),
      'value' => $selfRef,
    );
    $statistics['counts']['otherRef'] = array(
      'title' => ts('Other Referral'),
      'value' => $otherRef,
    );
    $statistics['counts']['referralSubTotal'] = array(
      'title' => ts('Total Served (Referral)'),
      'value' => $referralSubTotal,
    );

    //case notes fields
    //$provisionAssistiveDevices = $provisionAssistiveTech = $orientationAndMobilityTraining = $communicationSkills = $dailyLivingSkills = $supportiveServices = $advocacyTraining = $counseling = $communityIntegration = $otherILServices = 0;
    $statistics['counts']['provisionAssistiveDevices'] = array(
      'title' => ts('Provision of Assistive Technology devices and aids'),
      'value' => $provisionAssistiveDevices,
    );
    $statistics['counts']['provisionAssistiveTech'] = array(
      'title' => ts('Provision of Assistive Technology training'),
      'value' => $provisionAssistiveTech,
    );
    $statistics['counts']['orientationAndMobilityTraining'] = array(
      'title' => ts('Orientation and Mobility Training'),
      'value' => $orientationAndMobilityTraining,
    );
    $statistics['counts']['communicationSkills'] = array(
      'title' => ts('Communication Skills'),
      'value' => $communicationSkills,
    );
    $statistics['counts']['dailyLivingSkills'] = array(
      'title' => ts('Daily Living Skills'),
      'value' => $dailyLivingSkills,
    );
    $statistics['counts']['supportiveServices'] = array(
      'title' => ts('Supportive Services'),
      'value' => $supportiveServices,
    );
    $statistics['counts']['advocacyTraining'] = array(
      'title' => ts('Advocacy training and support networks'),
      'value' => $advocacyTraining,
    );
    $statistics['counts']['counseling'] = array(
      'title' => ts('Counseling'),
      'value' => $counseling,
    );
    $statistics['counts']['communityIntegration'] = array(
      'title' => ts('Information, referral and community integration'),
      'value' => $communityIntegration,
    );
    $statistics['counts']['otherILServices'] = array(
      'title' => ts('Other IL services'),
      'value' => $otherILServices,
    );

    //performance metrics
    //$receivingAtServices = $atServicesAndImproved = $atServicesNotDetermined = $oAndMTraining = $oAndMImproved = $oAndMNotDetermined = $commsTraining = $commsTrainingImproved = $commsTrainingNotDetermined = $dailyLivingTraining = $dailyLivingTrainingImproved = $dailyLivingTrainingNotDetermined = 0;
    $statistics['counts']['receivingAtServices'] = array(
      'title' => ts('Number of Individuals Receiving AT Services'),
      'value' => $receivingAtServices,
    );
    $statistics['counts']['atServicesAndImproved'] = array(
      'title' => ts('Number of Individuals receiving Assistive Technology training who maintained or improved functional abilities that were previously lost or diminished as a result of vision loss'),
      'value' => $atServicesAndImproved,
    );
    $statistics['counts']['atServicesNotDetermined'] = array(
      'title' => ts('Functional Gains Not Determined (AT Services)'),
      'value' => $atServicesNotDetermined,
    );
    $statistics['counts']['oAndMTraining'] = array(
      'title' => ts('O&M Training'),
      'value' => $oAndMTraining,
    );
    $statistics['counts']['oAndMImproved'] = array(
      'title' => ts('Of those receiving Orientation and Mobility services, the number of individuals who experienced functional gains or maintained their ability to travel safely and independently in their residence and/or community environment as a result of services.'),
      'value' => $oAndMImproved,
    );
    $statistics['counts']['oAndMNotDetermined'] = array(
      'title' => ts('Functional Gains Not Determined (O&M)'),
      'value' => $oAndMNotDetermined,
    );
    $statistics['counts']['commsTraining'] = array(
      'title' => ts('Communications'),
      'value' => $commsTraining,
    );
    $statistics['counts']['commsTrainingImproved'] = array(
      'title' => ts('Of those receiving Communication Skills training, the number of individuals who gained or maintained their functional abilities as a result of services they received.'),
      'value' => $commsTrainingImproved,
    );
    $statistics['counts']['commsTrainingNotDetermined'] = array(
      'title' => ts('Functional Gains Not Determined (Communications)'),
      'value' => $commsTrainingNotDetermined,
    );
    $statistics['counts']['dailyLivingTraining'] = array(
      'title' => ts('Daily Living'),
      'value' => $dailyLivingTraining,
    );
    $statistics['counts']['dailyLivingTrainingImproved'] = array(
      'title' => ts('Number of Individuals that experienced functional gains or successfully restored or maintained their functional ability to engage in their customary daily life activities as a result of services or training in personal management and daily living skills.'),
      'value' => $dailyLivingTrainingImproved,
    );
    $statistics['counts']['dailyLivingTrainingNotDetermined'] = array(
      'title' => ts('Functional Gains Not Determined (Daily Living)'),
      'value' => $dailyLivingTrainingNotDetermined,
    );

    //case closure fields
    //$greaterConfidence = $lessConfidence = $noChange = $unrelatedChange = $died = $totalCaseClosures = $totalCaseClosuresWithImprovement = 0;
    $statistics['counts']['greaterConfidence'] = array(
      'title' => ts('Greater Confidence/Control'),
      'value' => $greaterConfidence,
    );
    $statistics['counts']['lessConfidence'] = array(
      'title' => ts('Less Confidence/Control'),
      'value' => $lessConfidence,
    );
    $statistics['counts']['noChange'] = array(
      'title' => ts('No Change'),
      'value' => $noChange,
    );
    $statistics['counts']['unrelatedChange'] = array(
      'title' => ts('Unrelated Change'),
      'value' => $unrelatedChange,
    );
    $statistics['counts']['died'] = array(
      'title' => ts('Died'),
      'value' => $died,
    );
    $statistics['counts']['totalCaseClosures'] = array(
      'title' => ts('Total number of individuals who have a Case Closure'),
      'value' => $totalCaseClosures,
    );
    $statistics['counts']['totalCaseClosuresWithImprovement'] = array(
      'title' => ts('Total number of individuals who have a Case Closure, that report increased ability to engage in daily life activities'),
      'value' => $totalCaseClosuresWithImprovement,
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
