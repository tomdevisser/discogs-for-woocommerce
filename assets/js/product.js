(function () {
  "use strict";

  /**
   * Format a track's artists into a readable string.
   */
  function formatArtists(artists) {
    if (!artists || !artists.length) {
      return "";
    }

    const parts = [];
    artists.forEach(function (artist, i) {
      let name = artist.anv || artist.name;
      // Strip Discogs disambiguation suffixes like " (2)".
      name = name.replace(/\s*\(\d+\)$/, "");
      parts.push(name);

      if (i < artists.length - 1 && artist.join) {
        parts.push(" " + artist.join + " ");
      } else if (i < artists.length - 1) {
        parts.push(", ");
      }
    });

    return parts.join("");
  }

  /**
   * Format a single track as a text line.
   */
  function formatTrackLine(t) {
    const artist = formatArtists(t.artists);
    const trackNum = t.position ? t.position.replace(/^\d+-/, "") : "";
    const parts = [trackNum ? trackNum + "." : ""];
    if (artist) {
      parts.push(artist + " -");
    }
    parts.push(t.title);
    if (t.duration) {
      parts.push("(" + t.duration + ")");
    }
    return parts.join(" ");
  }

  /**
   * Format a tracklist as plain text for use in templates.
   * Groups tracks by disc when positions contain a disc prefix (e.g. "1-1").
   */
  function formatTracklistText(tracklist) {
    if (!tracklist || !tracklist.length) {
      return "";
    }

    const tracks = tracklist.filter(function (t) {
      return t.type_ === "track";
    });

    const hasDiscs = tracks.some(function (t) {
      return /^\d+-/.test(t.position);
    });

    if (!hasDiscs) {
      return tracks.map(formatTrackLine).join("\n");
    }

    const discs = {};
    tracks.forEach(function (t) {
      const match = t.position.match(/^(\d+)-/);
      const discNum = match ? match[1] : "1";
      if (!discs[discNum]) {
        discs[discNum] = [];
      }
      discs[discNum].push(t);
    });

    const discKeys = Object.keys(discs).sort(function (a, b) {
      return Number(a) - Number(b);
    });

    return discKeys
      .map(function (key) {
        return "CD " + key + "\n" + discs[key].map(formatTrackLine).join("\n");
      })
      .join("\n\n");
  }

  /**
   * Process the description template with release data.
   */
  function processTemplate(release) {
    const tpl = dfwProduct.descriptionTpl;

    if (!tpl) {
      return "";
    }

    const formats = (release.formats || []).map(function (f) {
      return f.name;
    });

    const replacements = {
      "[title]": release.title || "",
      "[artist]": release.artists_sort || "",
      "[year]": release.year ? String(release.year) : "",
      "[country]": release.country || "",
      "[format]": formats.join(", "),
      "[genre]": (release.genres || []).join(", "),
      "[tracklist]": formatTracklistText(release.tracklist),
    };

    let result = tpl;
    Object.keys(replacements).forEach(function (key) {
      result = result.split(key).join(replacements[key]);
    });

    return result;
  }

  /**
   * Build a table for a list of tracks.
   */
  function buildTrackTable(tracks) {
    let html =
      "<table><thead><tr>" +
      "<th>#</th>" +
      "<th>Artist</th>" +
      "<th>Title</th>" +
      "<th>Duration</th>" +
      "</tr></thead><tbody>";

    tracks.forEach(function (track) {
      const trackNum = track.position
        ? track.position.replace(/^\d+-/, "")
        : "";

      html +=
        "<tr>" +
        "<td>" +
        trackNum +
        "</td>" +
        "<td>" +
        formatArtists(track.artists) +
        "</td>" +
        "<td>" +
        track.title +
        "</td>" +
        "<td>" +
        (track.duration || "") +
        "</td>" +
        "</tr>";
    });

    html += "</tbody></table>";
    return html;
  }

  /**
   * Build the tracklist HTML.
   */
  function buildTracklist(tracklist) {
    if (!tracklist || !tracklist.length) {
      return "";
    }

    const tracks = tracklist.filter(function (t) {
      return t.type_ === "track";
    });

    const hasDiscs = tracks.some(function (t) {
      return /^\d+-/.test(t.position);
    });

    let html =
      '<div class="dfw-tracklist">' + "<h3>" + dfwProduct.tracklist + "</h3>";

    if (!hasDiscs) {
      html += buildTrackTable(tracks);
    } else {
      const discs = {};
      tracks.forEach(function (t) {
        const match = t.position.match(/^(\d+)-/);
        const discNum = match ? match[1] : "1";
        if (!discs[discNum]) {
          discs[discNum] = [];
        }
        discs[discNum].push(t);
      });

      Object.keys(discs)
        .sort(function (a, b) {
          return Number(a) - Number(b);
        })
        .forEach(function (key) {
          html += "<h4>CD " + key + "</h4>" + buildTrackTable(discs[key]);
        });
    }

    html += "</div>";
    return html;
  }

  /**
   * Build the images preview HTML.
   */
  function buildImages(images) {
    if (!images || !images.length) {
      return "";
    }

    const primary =
      images.find(function (img) {
        return img.type === "primary";
      }) || images[0];

    const secondary = images.filter(function (img) {
      return img !== primary;
    });

    let html =
      '<div class="dfw-images">' +
      '<div class="dfw-image-primary">' +
      "<h3>" +
      dfwProduct.productImage +
      "</h3>" +
      '<label class="dfw-image-label">' +
      '<img src="' +
      primary.uri150 +
      '" width="150" height="150" alt="">' +
      '<input type="checkbox" checked data-field="image" data-uri="' +
      primary.uri +
      '">' +
      "</label>" +
      "</div>";

    if (secondary.length) {
      html +=
        '<div class="dfw-image-gallery">' +
        "<h3>" +
        dfwProduct.gallery +
        "</h3>" +
        '<div class="dfw-gallery-thumbs">';

      secondary.forEach(function (img) {
        html +=
          '<label class="dfw-gallery-label">' +
          '<img src="' +
          img.uri150 +
          '" width="50" height="50" alt="">' +
          '<input type="checkbox" checked data-field="gallery" data-uri="' +
          img.uri +
          '">' +
          "</label>";
      });

      html += "</div></div>";
    }

    html += "</div>";
    return html;
  }

  /**
   * Build the description preview HTML.
   */
  function buildDescription(release) {
    const text = processTemplate(release);

    if (!text) {
      return "";
    }

    return (
      '<div class="dfw-description">' +
      '<h3><label><input type="checkbox" checked data-field="description"> ' +
      dfwProduct.description +
      "</label></h3>" +
      '<pre class="dfw-description-preview">' +
      text +
      "</pre>" +
      "</div>"
    );
  }

  /**
   * Build the modal HTML for a full release.
   */
  function buildModal(release) {
    const fields = [
      { key: "title", label: dfwProduct.labelTitle },
      { key: "artists_sort", label: dfwProduct.labelArtist },
      { key: "year", label: dfwProduct.labelYear },
      { key: "country", label: dfwProduct.labelCountry },
      { key: "formats", label: dfwProduct.category },
      { key: "genres", label: dfwProduct.subcategory },
    ];

    let rows = "";
    fields.forEach(function (field) {
      let value = release[field.key];

      if (!value) {
        return;
      }

      if (field.key === "formats") {
        value = value
          .map(function (f) {
            return f.name;
          })
          .join(", ");
      } else if (Array.isArray(value)) {
        value = value.join(", ");
      }

      rows +=
        "<tr>" +
        "<th>" +
        field.label +
        "</th>" +
        "<td>" +
        value +
        "</td>" +
        '<td class="dfw-col-import"><input type="checkbox" checked data-field="' +
        field.key +
        '"></td>' +
        "</tr>";
    });

    const overlay = document.createElement("div");
    overlay.className = "dfw-modal-overlay";
    overlay.innerHTML =
      '<div class="dfw-modal">' +
      '<div class="dfw-modal-header">' +
      "<h2>" +
      dfwProduct.modalTitle +
      "</h2>" +
      '<button type="button" class="dfw-modal-close" aria-label="Close">&times;</button>' +
      "</div>" +
      '<div class="dfw-modal-body">' +
      buildImages(release.images) +
      "<table>" +
      "<thead><tr>" +
      "<th>" +
      dfwProduct.field +
      "</th>" +
      "<th>" +
      dfwProduct.value +
      "</th>" +
      '<th class="dfw-col-import">' +
      dfwProduct.import +
      "</th>" +
      "</tr></thead>" +
      "<tbody>" +
      rows +
      "</tbody>" +
      "</table>" +
      buildDescription(release) +
      buildTracklist(release.tracklist) +
      "</div>" +
      '<div class="dfw-modal-footer">' +
      '<button type="button" class="button dfw-modal-cancel">' +
      dfwProduct.cancel +
      "</button>" +
      '<button type="button" class="button button-primary dfw-modal-add">' +
      dfwProduct.addToProduct +
      "</button>" +
      "</div>" +
      "</div>";

    return overlay;
  }

  /**
   * Open the modal with Discogs results.
   */
  function openModal(release) {
    const overlay = buildModal(release);
    document.body.appendChild(overlay);

    function close() {
      overlay.remove();
    }

    overlay.querySelector(".dfw-modal-close").addEventListener("click", close);
    overlay.querySelector(".dfw-modal-cancel").addEventListener("click", close);

    overlay.addEventListener("click", function (e) {
      if (e.target === overlay) {
        close();
      }
    });

    overlay
      .querySelector(".dfw-modal-add")
      .addEventListener("click", function () {
        applyToProduct(overlay, release);
        close();
      });
  }

  /**
   * All fields handled server-side.
   */
  const serverFieldKeys = [
    "title",
    "artists_sort",
    "country",
    "year",
    "formats",
    "genres",
  ];

  /**
   * Apply checked fields from the modal to the product form.
   */
  function applyToProduct(overlay, release) {
    const checked = overlay.querySelectorAll(
      '.dfw-modal-body input[type="checkbox"]:checked',
    );

    const serverFields = {};
    const images = [];

    checked.forEach(function (cb) {
      const key = cb.dataset.field;

      if (key === "image" || key === "gallery") {
        images.push({
          uri: cb.dataset.uri,
          type: key,
        });
        return;
      }

      if (key === "description") {
        serverFields.description = processTemplate(release);
        return;
      }

      if (serverFieldKeys.indexOf(key) === -1) {
        return;
      }

      let value = release[key];
      if (key === "formats" && Array.isArray(value)) {
        value = value.map(function (f) {
          return f.name;
        });
      }
      serverFields[key] = value;
    });

    if (Object.keys(serverFields).length === 0 && images.length === 0) {
      return;
    }

    const postId = document.getElementById("post_ID");

    if (!postId) {
      return;
    }

    const body = new URLSearchParams({
      action: "dfw_apply_to_product",
      nonce: dfwProduct.nonce,
      product_id: postId.value,
      fields: JSON.stringify(serverFields),
      images: JSON.stringify(images),
    });

    fetch(dfwProduct.ajaxUrl, {
      method: "POST",
      body: body,
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (result) {
        if (result.success) {
          window.location.reload();
        } else {
          console.error("Failed to apply data:", result);
        }
      })
      .catch(function (error) {
        console.error("Apply data error:", error);
      });
  }

  document.addEventListener("DOMContentLoaded", function () {
    const field = document.getElementById("_global_unique_id");

    if (!field) {
      return;
    }

    const button = document.createElement("button");
    button.type = "button";
    button.className = "button dfw-fetch-btn";
    button.textContent = dfwProduct.fetchButton;
    button.style.display = "flex";
    button.style.clear = "both";
    button.style.marginTop = "12px";

    field.after(button);

    button.addEventListener("click", function () {
      const barcode = field.value.trim();

      if (!barcode) {
        return;
      }

      button.disabled = true;
      button.textContent = dfwProduct.fetching;

      const params = new URLSearchParams({
        action: "dfw_search_barcode",
        nonce: dfwProduct.nonce,
        barcode: barcode,
      });

      fetch(dfwProduct.ajaxUrl + "?" + params.toString())
        .then(function (response) {
          return response.json();
        })
        .then(function (result) {
          if (result.success && result.data) {
            openModal(result.data);
          } else {
            alert(dfwProduct.noResults);
          }
        })
        .catch(function (error) {
          console.error("Discogs fetch error:", error);
        })
        .finally(function () {
          button.disabled = false;
          button.textContent = dfwProduct.fetchButton;
        });
    });
  });
})();
