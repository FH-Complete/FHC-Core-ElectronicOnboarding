<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Manages Onboarding file operations
 */
class OnboardingAkteLib
{
	/**
	 * Object initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->load->model('content/TempFS_model', 'TempFSModel');

		$this->_ci->load->library('AkteLib', array('who' => OnboardingRegistrierungLib::INSERT_VON));
	}

	// --------------------------------------------------------------------------------------------
	// Public methods
	

	/**
	 * 
	 * @param
	 * @return object success or error
	 */
	public function saveBigImage($person_id, $akte)
	{
		if (!isset($akte['file_content']) || isEmptyString($akte['file_content'])) return success("No file content found");

		$akte['file_content'] = $this->_resizeBase64ImageBig($akte['file_content']);

		return $this->_saveAkte($person_id, $akte);
	}

	/**
	 * Inserts or updates a document of a person as an akte.
	 * @param int $person_id
	 * @param array $akte
	 * @return int|null akte_id of inserted or updatedakte, null if nothing upserted
	 */
	private function _saveAkte($person_id, $akte)
	{
		$dokument_kurzbz = $akte['dokument_kurzbz'];
		$akteResp = null;

		if (isset($akte['dokument_bezeichnung']))
		{
			// prepend file name to title ending
			$akte['titel'] = $akte['dokument_bezeichnung'] . '_' . $person_id . $akte['titel'];

			// write temporary file
			$tempFileName = uniqid();
			$fileHandleResult = $this->_writeTempFile($tempFileName, base64_decode($akte['file_content']));

			if (hasData($fileHandleResult))
			{
				$fileHandle = getData($fileHandleResult);

				// save new akte
				$akteResp = $this->_ci->aktelib->add(
					$person_id,
					$dokument_kurzbz,
					$akte['titel'],
					$akte['mimetype'],
					$fileHandle,
					$akte['dokument_bezeichnung']
				);

				// close and delete the temporary file
				$this->_ci->TempFSModel->close($fileHandle);
				$this->_ci->TempFSModel->remove($tempFileName);
			}
		}

		return $akteResp;
	}

	/**
	 * Extracts mimetype from file data.
	 * @param $fileContent file data (image data, not encoded)
	 * @return string
	 */
	public function getMimeTypeFromFile($fileContent)
	{
		$file_info = new finfo(FILEINFO_MIME_TYPE);
		$mimetype = $file_info->buffer($fileContent);

		if (is_string($mimetype))
			return $mimetype;

		return null;
	}

	/**
	 * Makes sure base 64 image is not bigger than thumbnail size.
	 * @param string $image
	 * @return string resized image
	 */
	public function resizeBase64ImageSmall($image)
	{
		return $this->_resizeBase64Image($image, 101, 130);
	}

	// --------------------------------------------------------------------------------------------
	// Private methods

		/**
	 * Writes temporary file to file system.
	 * Used as template for saving documents to dms.
	 * @param string $filename
	 * @param string $file_content
	 * @return object containing pointer to written file
	 */
	private function _writeTempFile($filename, $file_content)
	{
		$readWriteResult = $this->_ci->TempFSModel->openReadWrite($filename);

		if (isError($readWriteResult))
			return $readWriteResult;

		$readWriteFileHandle = getData($readWriteResult);
		$writtenTemp = $this->_ci->TempFSModel->write($readWriteFileHandle, $file_content);

		if (isError($writtenTemp))
			return $writtenTemp;

		return $this->_ci->TempFSModel->openRead($filename);
	}

	/**
	 * Makes sure base 64 image is not bigger than max fhc db image size.
	 * @param $image
	 * @return string resized image
	 */
	private function _resizeBase64ImageBig($image)
	{
		return $this->_resizeBase64Image($image, 827, 1063);
	}

	/**
	 * If $image width/height is greater than given width/height, crop image, otherwise encode it.
	 * @param string $image as base 64 string
	 * @param int $maxWidth
	 * @param int $maxHeight
	 * @return string possibly cropped, base64 encoded image
	 */
	private function _resizeBase64Image($image, $maxWidth, $maxHeight)
	{
		$fhcImage = null;

		//groesse begrenzen
		$width = $maxWidth;
		$height = $maxHeight;
		$imageRaw = imagecreatefromstring(base64_decode($image));

		if ($imageRaw)
		{
			$uri = 'data://application/octet-stream;base64,' .$image;
			list($width_orig, $height_orig) = getimagesize($uri);

			$ratio_orig = $width_orig/$height_orig;

			if ($width_orig > $width || $height_orig > $height)
			{
				//keep proportions
				if ($width/$height > $ratio_orig)
				{
					$width = $height*$ratio_orig;
				}
				else
				{
					$height = $width/$ratio_orig;
				}

				$bg = imagecreatetruecolor($width, $height);
				imagecopyresampled($bg, $imageRaw, 0, 0, 0, 0, $width, $height, $width_orig, $height_orig);

				ob_start();
				imagejpeg($bg);
				$contents =  ob_get_contents();
				ob_end_clean();

				$fhcImage = base64_encode($contents);
			}
			else
				$fhcImage = $image;

			imagedestroy($imageRaw);
		}

		return $fhcImage;
	}
}
