/**
 * Gemeinsamer Place-Picker für Google Maps Platform Extended Component Library
 * Verdrahtet <gmpx-place-autocomplete> mit Zielfeldern und zerlegt Adresskomponenten
 */

function initPlacePicker(autocompleteEl, fieldMap) {
  if (!autocompleteEl) {
    console.warn('initPlacePicker: autocompleteEl nicht gefunden');
    return;
  }

  autocompleteEl.addEventListener('gmpx-placechange', async (e) => {
    const place = e.detail.place;
    if (!place) return;

    try {
      await place.fetchFields({
        fields: ['displayName', 'addressComponents', 'location', 'websiteURI']
      });

      let route = '', num = '', city = '', postal = '';

      if (place.addressComponents) {
        for (const component of place.addressComponents) {
          if (component.types.includes('route')) {
            route = component.longText || '';
          }
          if (component.types.includes('street_number')) {
            num = component.longText || '';
          }
          if (component.types.includes('locality')) {
            city = component.longText || '';
          }
          if (component.types.includes('postal_code')) {
            postal = component.longText || '';
          }
        }
      }

      const street = (route + ' ' + num).trim();
      const company = place.displayName || '';
      const website = place.websiteURI || '';
      const lat = place.location ? place.location.lat() : null;
      const lon = place.location ? place.location.lng() : null;

      const set = (key, val) => {
        const target = fieldMap[key];
        if (!target) return;

        if (typeof target === 'function') {
          target(val);
        } else {
          const el = document.getElementById(target);
          if (el) el.value = val || '';
        }
      };

      set('company', company);
      set('street', street);
      set('city', city);
      set('postal', postal);
      set('website', website);

      if (fieldMap.lat) fieldMap.lat(lat);
      if (fieldMap.lon) fieldMap.lon(lon);

    } catch (error) {
      console.error('initPlacePicker: Fehler beim Abrufen von Ort-Daten', error);
    }
  });
}
