/*
 @description		mootools based context menu
 @author			Daniel Niquet | http://utils.softr.net
 @based in			http://thinkweb2.com/projects/prototype
 @version			0.5
 @date				8/25/07
 @requires			mootools 1.11 (live fork: scripts/mootools.js)

 Not referenced by any template or page — kept for compatibility only. Menu labels are
 inserted as text instead of setHTML(), so caller-supplied names can never become markup,
 and the whole file degrades to a no-op when MooTools is absent.
*/
(function () {

	var setText = function (el, text) {
		text = (text == null) ? '' : String(text);
		if ('textContent' in el)
			el.textContent = text;
		else
			el.innerText = text;
		return el;
	};

	if (!window.Class || !window.Element) {
		window.AddContextMenu = window.AddContextMenu || function () {};
		return;
	}

	window.contextMenoo = new Class({
		options: {
			selector: '.contextmenu', className: '.protoMenu', pageOffset: 25, fade: false, headline: 'Menu'
		},
		initialize: function (op) {
			this.setOptions(op);
			this.cont = new Element('div', {'class': this.options.className});
			this.Fade = this.cont.effect('opacity');

			this.cont.adopt(setText(new Element('b', {'class': 'head'}), this.options.headline));
			var items = this.options.menuItems;
			if (!items)
				items = [];
			else if (typeof items.length !== 'number')
				items = [items];
			items.each(function (item) {
				if (item.separator) {
					this.cont.adopt(new Element('div', {'class': 'separator'}));
					return;
				}
				var link = new Element('a', {'href': '#', 'title': item.name, 'onclick': 'return false;', 'class': item.disabled ? 'disabled' : ''})
					.addEvent('click', this.onClick.bindWithEvent(this, [item.callback]));
				this.cont.adopt(setText(link, item.name));
			}.bind(this));
			this.Fade.set(0);
			$(document.body).adopt(this.cont);
			document.addEvents({
				'click': this.hide.bind(this), 'contextmenu': this.hide.bind(this)
			});

			$$(this.options.selector).each(function (el) {
				el.addEvent(window.opera ? 'click' : 'contextmenu', function (e) {
					if (window.opera && !e.ctrlKey) return;
					this.show(e);
				}.bind(this));
			}, this);
		},
		hide: function () { this.Fade.set(0); },
		show: function (e) {
			e = new DOMEvent(e).stop();
			var oCont = this.cont.getCoordinates(),
			size = {'height': window.getHeight(), 'width': window.getWidth(), 'top': window.getScrollTop(), 'cW': oCont.width, 'cH': oCont.height};

			this.cont.setStyles({
				left: ((e.page.x + size.cW + this.options.pageOffset) > size.width ? (size.width - size.cW - this.options.pageOffset) : e.page.x),
				top: ((e.page.y - size.top + size.cH) > size.height && (e.page.y - size.top) > size.cH ? (e.page.y - size.cH) : e.page.y)
			});
			this.Fade.set(0);
			this.options.fade ? this.Fade.start(0, 1) : this.Fade.set(1);
		},
		onClick: function (e, args) {
			if (e && e.target && e.target.hasClass && e.target.hasClass('disabled'))
				return;
			this.Fade.set(0);
			if (typeof args === 'function')
				args();
		}
	});
	contextMenoo.implement(new Options());

	window.AddContextMenu = function (select, classNames, fader, headl, oLinks) {
		window.addEvent('domready', function () {

			var menuObj = new contextMenoo({
				selector: select,
				className: classNames,
				fade: fader,
				menuItems: oLinks,
				headline: headl
			});

		});
	};

})();
