<?php

namespace Osimatic\Text;

/**
 * Contract for services that apply a digital signature to a PDF document.
 * Implementations decide how the signature is produced (certificate, remote signing service, etc.).
 *
 * @link https://en.wikipedia.org/wiki/PDF#Digital_signatures PDF digital signatures
 * @link https://en.wikipedia.org/wiki/Digital_signature Digital signature
 */
interface PDFSignerInterface
{
	/**
	 * Signs the PDF file located at the given input path and writes the signed document to the output path.
	 * The input file is left untouched unless the same path is used for both parameters.
	 *
	 * @param string $inputPath Path to the source PDF file to sign
	 * @param string $outputPath Path where the signed PDF file will be written
	 * @throws \RuntimeException If the input file cannot be read, the signature fails or the output file cannot be written
	 */
	public function sign(string $inputPath, string $outputPath): void;
}