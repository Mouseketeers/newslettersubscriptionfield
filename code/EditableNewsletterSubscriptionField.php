<?php
/**
 * Creates an editable field that displays members in a given group
 *
 * @package userforms
 */

class EditableNewsletterSubscriptionField extends EditableFormField {
	static $db = array(
		"Title" => "Varchar(255)",
		"Default" => "Varchar",
		"Sort" => "Int",
		"Required" => "Boolean",
		"CustomErrorMessage" => "Varchar(255)",
		"CustomRules" => "Text",
		"CustomSettings" => "Text",
		"CustomParameter" => "Varchar(200)"
	);
	
	static $singular_name = 'Newsletter Subscribe Field';
	
	static $plural_name = 'Newsletter Subscribe Fields';
	
	public function getIcon() {
		return 'mysite/images/editablenewslettersubscriptionfield.png';
	}
	function getFieldConfiguration() {
		//debug::message($this->ParentID);
		//debug::dump(DataObject::get('EditableFormField','ParentID = 24'));
		$newsletter_type_id = ($this->getSetting('NewsletterTypeID')) ? $this->getSetting('NewsletterTypeID') : 0;
		
		$emailFieldID = ($this->getSetting('EmailFieldID')) ? $this->getSetting('EmailFieldID') : '';
		$firstNameFieldID = ($this->getSetting('FirstNameFieldID')) ? $this->getSetting('FirstNameFieldID') : '';
		$lastNameFieldID = ($this->getSetting('LastNameFieldID')) ? $this->getSetting('LastNameFieldID') : '';
		
		$field_options = DataObject::get('EditableFormField','ParentID = '.$this->ParentID.' AND ClassName <> \'EditableNewsletterSubscriptionField\'');

		$newsletter_types = DataObject::get('NewsletterType');
		if($newsletter_types) $newsletter_type_options = $newsletter_types->toDropdownMap('ID', 'Title');
		$fields = new FieldSet(
			new CheckboxSetField("Fields[$this->ID][CustomSettings][NewsletterTypeID]", _t('EditableFormField.NEWSLETTERTYPE', 'Allow subscription to'), $newsletter_types, $newsletter_type_id),
			new DropdownField("Fields[$this->ID][CustomSettings][EmailFieldID]", _t('EditableFormField.EmailField', 'Select the field holding the e-mail'), $field_options->toDropdownMap('Name','Title', 'None'), $emailFieldID),
			new DropdownField("Fields[$this->ID][CustomSettings][FirstNameFieldID]", _t('EditableFormField.FirstNameField', 'Select the field holding the first name'), $field_options->toDropdownMap('Name','Title', 'None'), $firstNameFieldID),
			new DropdownField("Fields[$this->ID][CustomSettings][LastNameFieldID]", _t('EditableFormField.LastNameField', 'Select the field holding the last name'), $field_options->toDropdownMap('Name','Title', 'None'), $lastNameFieldID)
		);
		return $fields;
	}
	
	function getFormField() {
		$awailable_newsletters = $this->getSetting('NewsletterTypeID');
		$newsletters = DataObject::get('NewsletterType','ID IN('. implode(',', $awailable_newsletters) . ')');
		$newletters_form_field = new CheckboxSetField($this->Name,$this->Title,$newsletters);
		return $newletters_form_field;
	}
	function getValueFromData($data) {
		if(!$this->getSetting('EmailFieldID')) {
			user_error('Email for newsletter subscription missing', E_USER_WARNING);
			return false;
		}
		if(isset($data[$this->Name])) {
			$email = ($emailFieldID = $this->getSetting('EmailFieldID')) ? $data[$emailFieldID] : null;
			$firstName = ($firstNameFieldID = $this->getSetting('FirstNameFieldID')) ? $data[$firstNameFieldID] : null;
			$lastName = ($lastNameFieldID = $this->getSetting('LastNameFieldID')) ? $data[$lastNameFieldID] : null;
			$member = $this->addMember($email, $firstName, $lastName);
			$this->addSubscriptions($member, $data[$this->Name]);
		}
	}
	function addSubscriptions($member, $newsletters) {
		if($newsletters){
			foreach($newsletters as $newsletterID){
				$newsletterType = DataObject::get_by_id('NewsletterType', $newsletterID);
				
				if($newsletterType->exists()){
					$member->Groups()->add($newsletterType->GroupID);
					/*$member = new Member($data);
		$member->write();
 
		// Add the new member to the 'newslettersubscribe' group
		$groups = $member->Groups();
		$groups->setByCheckboxes(array("newslettersubscribe"), $data);
 
		// Write the Member table row
		$member->write();*/
				}
			}
		}
		$this->extend('onAfterAddSubscriptions');
	}
	function addMember($email, $firstName, $lastName = null) {
		if(!$email) {
			user_error('Email for newsletter subscription missing', E_USER_WARNING);
			return false;
		}
		$member = DataObject::get_one('Member', "\"Email\" = '". Convert::raw2sql($email) . "'");
		
		if(!$member) {
			$member = new Member();
			$member->Email = $email;
		}

		$member->FirstName = $firstName;
		$member->Surname = $lastName;
		$member->write();
		
		//for unsubscription auto-login
		if($member->AutoLoginHash){ 
			$member->AutoLoginExpired = date('Y-m-d', time() + (86400 * 2)); 
			$member->write(); 
		}else{ 
			$member->generateAutologinHash(); 
		}
		$this->extend('onAddMember', $member);
		return $member;
	}
}