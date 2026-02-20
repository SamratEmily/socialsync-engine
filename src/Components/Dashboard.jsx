import { useState, useCallback } from '@wordpress/element';
import axios from 'axios';
import ConnectionStatus from './ConnectionStatus';
import MediaDropzone from './MediaDropzone';
import PrivacyToggle from './PrivacyToggle';

/* Platform icon helpers ---------------------------------------------------- */

const FacebookIcon = () => (
    <svg className="w-8 h-8 text-[#1877F2]" viewBox="0 0 24 24" fill="currentColor">
        <path d="M24 12.073C24 5.404 18.627 0 12 0S0 5.404 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/>
    </svg>
);

const LinkedInIcon = () => (
    <svg className="w-8 h-8 text-[#0A66C2]" viewBox="0 0 24 24" fill="currentColor">
        <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
    </svg>
);

/* PrivacyCard --------------------------------------------------------------- */

/**
 * Combines a ConnectionStatus indicator with a PrivacyToggle into a single card.
 */
const PlatformCard = ( {
    platform, label, icon, accountLabel,
    connected, onDisconnect,
    privacyValue, onPrivacyChange,
    isPosting, enabled, onToggleEnabled,
} ) => {
    return (
        <div className={ `rounded-2xl border ${ enabled ? 'border-indigo-200 bg-white shadow-sm' : 'border-gray-200 bg-gray-50 opacity-60' } p-4 space-y-3 transition-all` }>
            {/* Platform header */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <input
                        id={ `sse-enable-${platform}` }
                        type="checkbox"
                        checked={ enabled }
                        disabled={ ! connected || isPosting }
                        onChange={ ( e ) => onToggleEnabled( e.target.checked ) }
                        className="w-4 h-4 rounded accent-indigo-600 cursor-pointer"
                    />
                    <label htmlFor={ `sse-enable-${platform}` } className="text-sm font-semibold text-gray-800 cursor-pointer">
                        Post to { label }
                    </label>
                </div>

                { connected && enabled && (
                    <PrivacyToggle
                        platform={ platform }
                        value={ privacyValue }
                        onChange={ onPrivacyChange }
                        disabled={ isPosting }
                    />
                ) }
            </div>

            {/* Connection status row */}
            <ConnectionStatus
                platform={ platform }
                connected={ connected }
                label={ label }
                icon={ icon }
                accountLabel={ accountLabel }
                onDisconnect={ onDisconnect }
            />
        </div>
    );
};

/* Dashboard ---------------------------------------------------------------- */

const Dashboard = ( { initialStatus, onStatusChange } ) => {
    const { apiBase } = window.socialSyncEngine ?? {};

    /* ---- State ---- */

    const [ caption, setCaption ]           = useState( '' );
    const [ mediaFile, setMediaFile ]       = useState( null );
    const [ status, setStatus ]             = useState( initialStatus );

    const [ fbEnabled, setFbEnabled ]       = useState( false );
    const [ liEnabled, setLiEnabled ]       = useState( false );

    const [ fbPrivacy, setFbPrivacy ]       = useState( 'public' );
    const [ liPrivacy, setLiPrivacy ]       = useState( 'public' );

    const [ isPosting, setIsPosting ]       = useState( false );
    const [ postResult, setPostResult ]     = useState( null );  // { success, results }
    const [ postError, setPostError ]       = useState( '' );

    /* ---- Refresh platform status ---- */

    const refreshStatus = useCallback( async () => {
        try {
            const { data } = await axios.get( `${apiBase}/status` );
            setStatus( data );
            onStatusChange?.();
        } catch {}
    }, [ apiBase, onStatusChange ] );

    /* ---- Publish ---- */

    const handlePublish = async ( e ) => {
        e.preventDefault();
        setPostResult( null );
        setPostError( '' );

        if ( ! caption.trim() ) {
            setPostError( 'Please enter a caption before publishing.' );
            return;
        }

        const selectedPlatforms = [
            ...( fbEnabled && status.facebook.connected ? [ 'facebook' ] : [] ),
            ...( liEnabled && status.linkedin.connected ? [ 'linkedin' ] : [] ),
        ];

        if ( selectedPlatforms.length === 0 ) {
            setPostError( 'Connect to at least one platform and enable it to post.' );
            return;
        }

        setIsPosting( true );

        try {
            const formData = new FormData();
            formData.append( 'caption',           caption );
            formData.append( 'platforms',          JSON.stringify( selectedPlatforms ) );
            formData.append( 'facebook_privacy',   JSON.stringify( { value: fbPrivacy } ) );
            formData.append( 'linkedin_privacy',   JSON.stringify( { value: liPrivacy } ) );

            if ( mediaFile ) {
                formData.append( 'media', mediaFile );
            }

            const { data } = await axios.post( `${apiBase}/publish`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            } );

            setPostResult( data );

            if ( data.success ) {
                setCaption( '' );
                setMediaFile( null );
                setFbEnabled( false );
                setLiEnabled( false );
            }

        } catch ( err ) {
            const msg = err.response?.data?.message ?? err.message ?? 'An unexpected error occurred.';
            setPostError( msg );
        } finally {
            setIsPosting( false );
        }
    };

    /* ---- Render ---- */

    return (
        <div className="max-w-2xl mx-auto space-y-6">

            {/* Platforms section */}
            <section>
                <h2 className="text-base font-semibold text-gray-700 mb-3">Connected Platforms</h2>
                <div className="space-y-3">
                    <PlatformCard
                        platform="facebook"
                        label="Facebook"
                        icon={ <FacebookIcon /> }
                        accountLabel={ status.facebook.page_name }
                        connected={ status.facebook.connected }
                        onDisconnect={ refreshStatus }
                        privacyValue={ fbPrivacy }
                        onPrivacyChange={ setFbPrivacy }
                        isPosting={ isPosting }
                        enabled={ fbEnabled }
                        onToggleEnabled={ setFbEnabled }
                    />

                    <PlatformCard
                        platform="linkedin"
                        label="LinkedIn"
                        icon={ <LinkedInIcon /> }
                        accountLabel={ status.linkedin.person_urn }
                        connected={ status.linkedin.connected }
                        onDisconnect={ refreshStatus }
                        privacyValue={ liPrivacy }
                        onPrivacyChange={ setLiPrivacy }
                        isPosting={ isPosting }
                        enabled={ liEnabled }
                        onToggleEnabled={ setLiEnabled }
                    />
                </div>
            </section>

            {/* Compose section */}
            <section>
                <h2 className="text-base font-semibold text-gray-700 mb-3">Compose Post</h2>

                <form onSubmit={ handlePublish } className="space-y-4">

                    {/* Caption */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Caption <span className="text-red-500">*</span>
                        </label>
                        <textarea
                            rows={ 5 }
                            value={ caption }
                            onChange={ ( e ) => setCaption( e.target.value ) }
                            placeholder="Write your post caption here…"
                            disabled={ isPosting }
                            className="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-y disabled:opacity-60"
                        />
                        <p className="text-right text-xs text-gray-400 mt-1">{ caption.length } chars</p>
                    </div>

                    {/* Media */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Media <span className="text-gray-400 font-normal">(optional)</span>
                        </label>
                        <MediaDropzone
                            file={ mediaFile }
                            onChange={ setMediaFile }
                            disabled={ isPosting }
                        />
                    </div>

                    {/* Error / success feedback */}
                    { postError && (
                        <div className="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                            { postError }
                        </div>
                    ) }

                    { postResult && (
                        <div className={ `rounded-xl border px-4 py-3 text-sm ${ postResult.success ? 'bg-green-50 border-green-200 text-green-700' : 'bg-yellow-50 border-yellow-200 text-yellow-700' }` }>
                            { postResult.success ? (
                                <p className="font-medium">Post published successfully!</p>
                            ) : (
                                <p className="font-medium">Some platforms failed:</p>
                            ) }

                            { Object.entries( postResult.results ?? {} ).map( ( [ platform, result ] ) => (
                                <p key={ platform } className="text-xs mt-1">
                                    <span className="capitalize font-semibold">{ platform }</span>
                                    { result.success
                                        ? ` ✔ ${ result.post_id ? `(ID: ${result.post_id})` : '' }`
                                        : ` ✖ ${ result.error ?? 'Failed' }` }
                                </p>
                            ) ) }
                        </div>
                    ) }

                    {/* Submit */}
                    <button
                        type="submit"
                        disabled={ isPosting }
                        className="w-full flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-400 text-white font-semibold text-sm px-6 py-3 rounded-xl transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-400"
                    >
                        { isPosting ? (
                            <>
                                <span className="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full" />
                                Publishing…
                            </>
                        ) : 'Publish Now' }
                    </button>

                </form>
            </section>
        </div>
    );
};

export default Dashboard;
