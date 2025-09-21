# Implementation Plan

- [x] 1. Update XML root structure and namespace
  - Analyze DPS XSD schema to identify correct namespace and root element structure
  - Modify `generateXmlFromData()` method in `NfSeDpsGenerator.php` to use XSD-compliant namespace
  - Add version attribute to root element as specified in XSD
  - Update main information element naming and casing to match XSD requirements
  - _Requirements: 1.1, 1.2, 1.3_

- [x] 2. Refactor identification fields structure
  - Analyze DPS XSD schema to identify correct identification field names and structure
  - Remove `addIdentificacaoDps()` method and integrate fields directly into main information element
  - Add all required identification fields directly to main element as per XSD specification
  - Update `buildDpsData()` method to provide correct field mapping based on XSD analysis
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7_

- [x] 3. Implement data sanitization helper methods
  - Add `sanitizePhone()` private method to extract digits only from phone numbers ✅ Implemented
  - Add `sanitizeServiceDescription()` private method to remove HTML tags using `strip_tags()` ✅ Implemented
  - Add `formatDateTimeISO8601()` private method for proper datetime formatting ✅ Implemented
  - _Requirements: 8.1, 8.2, 8.3, 8.5_

- [x] 4. Refactor provider (prestador) structure to XSD-compliant format
  - Analyze DPS XSD schema to identify correct provider element name and structure
  - Rename and refactor `addPrestador()` method to match XSD provider element name
  - Change from generic inscription structure to direct document identification as per XSD
  - Update `buildPrestadorData()` to return data compatible with XSD structure
  - _Requirements: 3.1, 3.2, 3.7_

- [x] 5. Update address structure to XSD-compliant format
  - Analyze DPS XSD schema to identify correct address element name and required fields
  - Refactor `addEndereco()` method to create XSD-compliant address structure
  - Add all required address fields as specified in XSD schema
  - Ensure all address fields follow XSD specification with proper field names and formats
  - _Requirements: 3.4, 3.5_

- [x] 6. Refactor customer (tomador) structure
  - Analyze DPS XSD schema to identify correct customer element name and structure
  - Rename and refactor `addTomador()` method to match XSD customer element name
  - Update customer structure to use direct document identification as per XSD
  - Update `buildTomadorData()` to return data compatible with XSD structure
  - _Requirements: 3.1, 3.3, 3.6_

- [x] 7. Implement service description sanitization
  - Analyze DPS XSD schema to identify service description field requirements
  - Update `buildServiceDescription()` method to remove HTML tags and entities
  - Ensure service description meets XSD plain text requirements
  - Maintain description length limits and minimum requirements as per XSD
  - _Requirements: 4.1, 8.3_

- [x] 8. Restructure service values according to XSD
  - Analyze DPS XSD schema to identify correct service and values element structure
  - Refactor `addServico()` method to separate service info and values as per XSD
  - Remove wrapper elements not required by XSD and implement individual value fields
  - Create separate value handling method based on XSD values structure
  - _Requirements: 4.2, 4.4_

- [x] 9. Implement ISS group structure
  - Analyze DPS XSD schema to identify ISS tax group structure and required fields
  - Add ISS group creation when ISS tax applies based on XSD specification
  - Include all required ISS fields with proper formatting as per XSD
  - Update tax calculation logic to populate ISS group correctly according to XSD
  - _Requirements: 4.3_

- [x] 10. Integrate XSD validation into generation pipeline
  - Add XSD validation call in `generateDpsXml()` method using existing `NfSeXsdValidator`
  - Ensure validation occurs after XML generation but before returning result
  - Include validation results in the return array
  - _Requirements: 6.1, 6.2, 6.4_

- [x] 11. Add XSD validation demonstration to manual emission interface
  - Update `html-admin-manual-emission.php` to display XSD validation results
  - Show validation status, errors, and warnings in the interface
  - Add validation step demonstration as specified in requirements
  - _Requirements: 6.3, 6.5_

- [x] 12. Fix DPS ID format to comply with official specification and XSD maxLength constraint
  - Implement official DPS ID format: "DPS" + Cód.Mun.(7) + Tipo Inscrição Federal(1) + Inscrição Federal(14) + Série DPS(5) + Núm. DPS(15)
  - Add proper document type detection (1=CPF, 2=CNPJ) in `generateDpsId()` method
  - Ensure CPF is padded with zeros to 14 positions as per specification
  - Use proper DPS series formatting (5 digits) from settings
  - Ensure total ID length is exactly 45 characters (XSD requirement)
  - _Requirements: 2.1, 6.1_

- [x] 13. Implement proper monetary value formatting as required by XSD
  - Ensure all monetary values use correct decimal formatting (2 places for currency, 4 for percentages)
  - Update `calculateIssRate()` if needed and related methods to format values correctly
  - Verify tax calculation precision meets XSD requirements
  - _Requirements: 8.4_

- [x] 14. Update date and datetime formatting
  - Ensure `dhEmi` field uses ISO8601 format (YYYY-MM-DDTHH:mm:ssTZD) ✅ Already implemented
  - Ensure `dCompet` field uses complete date format (YYYY-MM-DD) instead of (YYYYMMDD) ✅ Fixed
  - Update date processing throughout the DPS generation pipeline ✅ Complete
  - _Requirements: 2.3, 2.4, 8.5_

- [ ] 15. Test and validate complete XML structure
  - Create comprehensive test to validate generated XML against `DPS_v1.00.xsd`
  - Test with different order types (individual, company, various services)
  - Verify all field mappings work correctly with real order data
  - _Requirements: 6.1, 6.2_

- [ ] 16. Update digital signature target element
  - Analyze DPS XSD schema to identify correct signature target element
  - Ensure digital signature targets the main information element as per XSD
  - Verify signature reference URI uses the correct Id attribute format
  - Test signature validation with updated XML structure
  - _Requirements: 5.1, 5.5_

- [ ] 17. Implement output format options
  - Ensure `generateDpsXml()` returns XML as string format
  - Add Base64 encoding option for API transmission
  - Verify XML escaping for JSON embedding works correctly
  - _Requirements: 7.1, 7.2, 7.4_

- [ ] 18. Create comprehensive validation and testing suite
  - Update existing test methods to work with new XML structure
  - Add tests for data sanitization functions
  - Create integration tests for complete generation pipeline
  - _Requirements: 6.1, 6.2, 6.4_