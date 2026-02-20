import { useState, useRef, useCallback } from '@wordpress/element';

const ACCEPTED_TYPES = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'video/mp4', 'video/quicktime' ];
const MAX_IMAGE_MB   = 10;
const MAX_VIDEO_MB   = 1024;

/**
 * MediaDropzone
 *
 * A drag-and-drop upload area that accepts images and videos.
 *
 * Props:
 *   file      File | null  – currently selected file (controlled)
 *   onChange  fn(File|null)
 *   disabled  bool
 */
const MediaDropzone = ( { file, onChange, disabled = false } ) => {
    const [ isDragOver, setIsDragOver ] = useState( false );
    const [ error, setError ]           = useState( '' );
    const inputRef                      = useRef( null );

    const validate = useCallback( ( f ) => {
        if ( ! ACCEPTED_TYPES.includes( f.type ) ) {
            return 'Unsupported file type. Use JPEG, PNG, GIF, WebP, MP4, or MOV.';
        }

        const isVideo  = f.type.startsWith( 'video/' );
        const maxBytes = isVideo ? MAX_VIDEO_MB * 1_048_576 : MAX_IMAGE_MB * 1_048_576;

        if ( f.size > maxBytes ) {
            return `File too large. Max is ${ isVideo ? MAX_VIDEO_MB + ' MB' : MAX_IMAGE_MB + ' MB' }.`;
        }

        return '';
    }, [] );

    const handleFile = useCallback( ( f ) => {
        const err = validate( f );
        setError( err );

        if ( ! err ) {
            onChange( f );
        }
    }, [ validate, onChange ] );

    const handleDrop = useCallback( ( e ) => {
        e.preventDefault();
        setIsDragOver( false );

        if ( disabled ) return;

        const dropped = e.dataTransfer.files[0];
        if ( dropped ) handleFile( dropped );
    }, [ disabled, handleFile ] );

    const handleDragOver = ( e ) => { e.preventDefault(); if ( ! disabled ) setIsDragOver( true ); };
    const handleDragLeave = () => setIsDragOver( false );

    const handleInputChange = ( e ) => {
        const f = e.target.files[0];
        if ( f ) handleFile( f );
    };

    const clearFile = ( e ) => {
        e.stopPropagation();
        setError( '' );
        onChange( null );
        if ( inputRef.current ) inputRef.current.value = '';
    };

    const preview = file ? URL.createObjectURL( file ) : null;
    const isVideo  = file?.type.startsWith( 'video/' );

    return (
        <div
            role="button"
            tabIndex={ disabled ? -1 : 0 }
            onClick={ () => ! disabled && inputRef.current?.click() }
            onKeyDown={ ( e ) => e.key === 'Enter' && ! disabled && inputRef.current?.click() }
            onDrop={ handleDrop }
            onDragOver={ handleDragOver }
            onDragLeave={ handleDragLeave }
            className={
                `relative flex flex-col items-center justify-center rounded-2xl border-2 border-dashed transition-colors min-h-[180px] cursor-pointer ` +
                ( isDragOver
                    ? 'border-indigo-400 bg-indigo-50'
                    : 'border-gray-300 bg-white hover:border-indigo-300 hover:bg-indigo-50/40' ) +
                ( disabled ? ' opacity-60 cursor-not-allowed' : '' )
            }
        >
            <input
                ref={ inputRef }
                type="file"
                accept={ ACCEPTED_TYPES.join( ',' ) }
                className="sr-only"
                onChange={ handleInputChange }
                disabled={ disabled }
            />

            { file ? (
                <div className="relative w-full p-3 flex flex-col items-center gap-2">
                    { isVideo ? (
                        <video
                            src={ preview }
                            className="max-h-40 rounded-lg object-cover"
                            controls
                        />
                    ) : (
                        <img
                            src={ preview }
                            alt="Preview"
                            className="max-h-40 rounded-lg object-contain"
                        />
                    ) }
                    <p className="text-xs text-gray-500 truncate max-w-xs">{ file.name }</p>

                    { ! disabled && (
                        <button
                            type="button"
                            onClick={ clearFile }
                            className="absolute top-1 right-1 bg-white text-gray-500 hover:text-red-600 rounded-full p-1 shadow transition-colors focus:outline-none"
                            title="Remove media"
                        >
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={ 2 } d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    ) }
                </div>
            ) : (
                <div className="flex flex-col items-center gap-2 p-6 text-center pointer-events-none select-none">
                    <svg className="w-10 h-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={ 1.5 }
                            d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                    </svg>
                    <p className="text-sm text-gray-500">
                        <span className="font-medium text-indigo-600">Upload a file</span> or drag and drop
                    </p>
                    <p className="text-xs text-gray-400">JPEG, PNG, GIF, WebP up to 10 MB · MP4, MOV up to 1 GB</p>
                </div>
            ) }

            { error && (
                <p className="absolute bottom-2 text-xs text-red-500 px-4 text-center">{ error }</p>
            ) }
        </div>
    );
};

export default MediaDropzone;
