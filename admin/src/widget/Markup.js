/**
 * The screenshot with marks drawn on it: box, arrow, freehand, text and blur.
 * The marks are kept as shapes and drawn onto the picture's own pixels, so
 * what is sent is exactly what is shown.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const COLOR = '#ea580c';

/**
 * Draw the picture and its marks onto a canvas of the picture's size.
 *
 * @param {HTMLCanvasElement} canvas Where to draw.
 * @param {HTMLCanvasElement} base   The screenshot.
 * @param {Array}             shapes The marks.
 */
export function paint( canvas, base, shapes ) {
	canvas.width = base.width;
	canvas.height = base.height;
	const ctx = canvas.getContext( '2d' );
	ctx.drawImage( base, 0, 0 );

	// Blur first, so a mark on top of a blurred area stays sharp.
	shapes
		.filter( ( s ) => s.type === 'blur' )
		.forEach( ( s ) => pixelate( ctx, base, box( s ) ) );

	const line = Math.max( 3, Math.round( base.width / 400 ) );
	ctx.lineWidth = line;
	ctx.lineCap = 'round';
	ctx.lineJoin = 'round';
	ctx.strokeStyle = COLOR;
	ctx.fillStyle = COLOR;

	shapes.forEach( ( s ) => {
		if ( s.type === 'box' ) {
			const b = box( s );
			ctx.strokeRect( b.x, b.y, b.w, b.h );
		} else if ( s.type === 'arrow' ) {
			arrow( ctx, s, line );
		} else if ( s.type === 'pen' && s.points.length > 1 ) {
			ctx.beginPath();
			ctx.moveTo( s.points[ 0 ][ 0 ], s.points[ 0 ][ 1 ] );
			s.points.forEach( ( p ) => ctx.lineTo( p[ 0 ], p[ 1 ] ) );
			ctx.stroke();
		} else if ( s.type === 'text' && s.text ) {
			const size = Math.max( 16, Math.round( base.width / 70 ) );
			ctx.font = '600 ' + size + 'px system-ui, sans-serif';
			ctx.lineWidth = Math.round( size / 4 );
			ctx.strokeStyle = '#ffffff';
			ctx.strokeText( s.text, s.x1, s.y1 );
			ctx.fillText( s.text, s.x1, s.y1 );
			ctx.lineWidth = line;
			ctx.strokeStyle = COLOR;
		}
	} );
}

function box( s ) {
	return {
		x: Math.min( s.x1, s.x2 ),
		y: Math.min( s.y1, s.y2 ),
		w: Math.abs( s.x2 - s.x1 ),
		h: Math.abs( s.y2 - s.y1 ),
	};
}

function pixelate( ctx, base, b ) {
	if ( b.w < 2 || b.h < 2 ) {
		return;
	}
	const block = Math.max( 8, Math.round( base.width / 120 ) );
	const small = document.createElement( 'canvas' );
	small.width = Math.max( 1, Math.ceil( b.w / block ) );
	small.height = Math.max( 1, Math.ceil( b.h / block ) );
	small
		.getContext( '2d' )
		.drawImage( base, b.x, b.y, b.w, b.h, 0, 0, small.width, small.height );
	ctx.save();
	ctx.imageSmoothingEnabled = false;
	ctx.drawImage( small, 0, 0, small.width, small.height, b.x, b.y, b.w, b.h );
	ctx.restore();
}

function arrow( ctx, s, line ) {
	const angle = Math.atan2( s.y2 - s.y1, s.x2 - s.x1 );
	const head = line * 5;
	ctx.beginPath();
	ctx.moveTo( s.x1, s.y1 );
	ctx.lineTo( s.x2, s.y2 );
	ctx.stroke();
	ctx.beginPath();
	ctx.moveTo( s.x2, s.y2 );
	ctx.lineTo(
		s.x2 - head * Math.cos( angle - Math.PI / 6 ),
		s.y2 - head * Math.sin( angle - Math.PI / 6 )
	);
	ctx.lineTo(
		s.x2 - head * Math.cos( angle + Math.PI / 6 ),
		s.y2 - head * Math.sin( angle + Math.PI / 6 )
	);
	ctx.closePath();
	ctx.fill();
}

/**
 * @param {Object}   root0           Props.
 * @param {Object}   root0.base      The screenshot canvas.
 * @param {Array}    root0.shapes    The marks.
 * @param {Function} root0.setShapes Called with the new marks.
 * @param {string}   root0.tool      box, arrow, pen, text or blur.
 * @return {Object} Stage element.
 */
export default function Markup( { base, shapes, setShapes, tool } ) {
	const canvasRef = useRef();
	const [ draft, setDraft ] = useState( null );
	const [ typing, setTyping ] = useState( null ); // { x1, y1, left, top }
	const [ text, setText ] = useState( '' );
	const textRef = useRef();

	useEffect( () => {
		if ( typing && textRef.current ) {
			textRef.current.focus( { preventScroll: true } );
		}
	}, [ typing ] );

	const all = draft ? shapes.concat( [ draft ] ) : shapes;

	useEffect( () => {
		if ( canvasRef.current && base ) {
			paint( canvasRef.current, base, all );
		}
	}, [ base, all ] );

	const at = ( event ) => {
		const rect = canvasRef.current.getBoundingClientRect();
		const ratio = base.width / rect.width;
		return {
			x: Math.round( ( event.clientX - rect.left ) * ratio ),
			y: Math.round( ( event.clientY - rect.top ) * ratio ),
			left: event.clientX - rect.left,
			top: event.clientY - rect.top,
		};
	};

	const commitText = () => {
		if ( typing && text.trim() ) {
			setShapes(
				shapes.concat( [
					{ type: 'text', x1: typing.x1, y1: typing.y1, text },
				] )
			);
		}
		setTyping( null );
		setText( '' );
	};

	const down = ( event ) => {
		const p = at( event );
		if ( tool === 'text' ) {
			// Keep the click from moving the focus, which would close the box at once.
			event.preventDefault();
			commitText();
			setTyping( { x1: p.x, y1: p.y, left: p.left, top: p.top } );
			return;
		}
		event.currentTarget.setPointerCapture( event.pointerId );
		setDraft(
			tool === 'pen'
				? { type: 'pen', points: [ [ p.x, p.y ] ] }
				: { type: tool, x1: p.x, y1: p.y, x2: p.x, y2: p.y }
		);
	};

	const move = ( event ) => {
		if ( ! draft ) {
			return;
		}
		const p = at( event );
		setDraft(
			draft.type === 'pen'
				? { ...draft, points: draft.points.concat( [ [ p.x, p.y ] ] ) }
				: { ...draft, x2: p.x, y2: p.y }
		);
	};

	const up = () => {
		if ( draft ) {
			const big =
				draft.type === 'pen'
					? draft.points.length > 2
					: Math.abs( draft.x2 - draft.x1 ) +
							Math.abs( draft.y2 - draft.y1 ) >
					  6;
			if ( big ) {
				setShapes( shapes.concat( [ draft ] ) );
			}
			setDraft( null );
		}
	};

	return (
		<div className="stage">
			<canvas
				ref={ canvasRef }
				className={ 'stage__canvas is-' + tool }
				onPointerDown={ down }
				onPointerMove={ move }
				onPointerUp={ up }
				onPointerCancel={ up }
				aria-label={ __(
					'Screenshot of the page. Draw on it to point at the problem.',
					'nhrrob-issue-reporter'
				) }
				role="img"
			/>
			{ typing && (
				<input
					className="stage__text"
					style={ { left: typing.left, top: typing.top } }
					ref={ textRef }
					value={ text }
					placeholder={ __(
						'Type, then Enter',
						'nhrrob-issue-reporter'
					) }
					aria-label={ __(
						'Text on the screenshot',
						'nhrrob-issue-reporter'
					) }
					onChange={ ( e ) => setText( e.target.value ) }
					onBlur={ commitText }
					onKeyDown={ ( e ) => {
						if ( e.key === 'Enter' ) {
							e.preventDefault();
							commitText();
						} else if ( e.key === 'Escape' ) {
							e.stopPropagation();
							setTyping( null );
							setText( '' );
						}
					} }
				/>
			) }
		</div>
	);
}
